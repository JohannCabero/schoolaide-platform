<?php

namespace App\Jobs;

use App\Models\ImportLog;
use App\Models\ServiceRequest;
use App\Models\ServiceType;
use App\Models\Student;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ProcessImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Max retry attempts before marking import as failed.
     */
    public int $tries = 3;

    /**
     * Timeout in seconds (10 minutes for large files).
     */
    public int $timeout = 600;

    public function __construct(
        private readonly int $importLogId,
        private readonly int $tenantId  // Explicit tenant context for background jobs
    ) {}

    public function handle(): void
    {
        // Set the tenant context explicitly — TenantMiddleware doesn't run for jobs
        $tenant = Tenant::find($this->tenantId);

        if (! $tenant) {
            Log::error("ProcessImportJob: Tenant {$this->tenantId} not found.");

            return;
        }

        app()->instance('currentTenant', $tenant);

        $importLog = ImportLog::withoutGlobalScopes()->find($this->importLogId);

        if (! $importLog) {
            Log::error("ProcessImportJob: ImportLog {$this->importLogId} not found.");

            return;
        }

        $importLog->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);

        try {
            $results = $this->processFile($importLog, $tenant->id);

            $importLog->update([
                'status' => 'completed',
                'completed_at' => now(),
                'total_rows' => $results['total'],
                'successful_rows' => $results['success'],
                'skipped_rows' => $results['skipped'],
                'summary_json' => $results['details'],
            ]);
        } catch (\Throwable $e) {
            Log::error("ProcessImportJob failed for ImportLog #{$this->importLogId}: " . $e->getMessage());

            $importLog->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_message' => $e->getMessage(),
            ]);

            throw $e; // Re-throw so the queue retries
        }
    }

    private function processFile(ImportLog $importLog, int $tenantId): array
    {
        $path = Storage::path($importLog->filename);

        $rows = Excel::toArray([], $path)[0];
        $header = array_shift($rows); // Skip header row
        $header = array_map('strtolower', array_map('trim', $header));

        $total = 0;
        $success = 0;
        $skipped = 0;
        $details = [];

        // Process in chunks of 500 to avoid memory exhaustion
        $chunks = array_chunk($rows, 500);

        foreach ($chunks as $chunk) {
            foreach ($chunk as $rowIndex => $row) {
                $total++;
                $lineNumber = $total + 1; // +1 because removed header

                if (count($row) !== count($header)) {
                    $skipped++;
                    $details[] = $this->skippedRow($lineNumber, 'Column count does not match header.');
                    continue;
                }

                $data = array_combine($header, $row);

                $result = $this->processRow($data, $tenantId, $lineNumber);

                if ($result['success']) {
                    $success++;
                } else {
                    $skipped++;
                    $details[] = $result;
                }
            }
        }

        return compact('total', 'success', 'skipped', 'details');
    }

    private function processRow(array $data, int $tenantId, int $lineNumber): array
    {
        $studentNumber = trim($data['student number'] ?? $data['student_number'] ?? '');
        $serviceTypeCode = trim($data['service type'] ?? $data['service_type'] ?? '');
        $requestedDate = trim($data['requested date'] ?? $data['requested_date'] ?? '');

        // Rule 1: Missing Student Number
        if (empty($studentNumber)) {
            return $this->skippedRow($lineNumber, 'Missing student number.');
        }

        // Rule 2: Student doesn't exist
        $student = Student::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('student_number', $studentNumber)
            ->whereNull('deleted_at')
            ->first();

        if (! $student) {
            return $this->skippedRow($lineNumber, "Student '{$studentNumber}' not found.");
        }

        // Rule 3: Student is inactive
        if (! $student->isActive()) {
            return $this->skippedRow($lineNumber, "Student '{$studentNumber}' is not active (status: {$student->status}).");
        }

        // Rule 4: Service type is invalid
        $serviceType = ServiceType::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('code', strtoupper($serviceTypeCode))
            ->where('is_active', true)
            ->first();

        if (! $serviceType) {
            return $this->skippedRow($lineNumber, "Service type '{$serviceTypeCode}' is not valid.");
        }

        // Validate and parse date
        try {
            $parsedDate = \Carbon\Carbon::parse($requestedDate)->toDateString();
        } catch (\Throwable) {
            return $this->skippedRow($lineNumber, "Invalid requested date: '{$requestedDate}'.");
        }

        // Rule 5: Duplicate request
        $duplicate = ServiceRequest::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('student_id', $student->id)
            ->where('service_type_id', $serviceType->id)
            ->whereDate('requested_date', $parsedDate)
            ->exists();

        if ($duplicate) {
            return $this->skippedRow($lineNumber, "Duplicate request for student '{$studentNumber}' with service type '{$serviceTypeCode}' on {$parsedDate}.");
        }

        // All validations passed — create the request
        try {
            DB::transaction(function () use ($tenantId, $student, $serviceType, $parsedDate) {
                ServiceRequest::create([
                    'tenant_id' => $tenantId,
                    'student_id' => $student->id,
                    'service_type_id' => $serviceType->id,
                    'requested_date' => $parsedDate,
                    'status' => 'pending',
                    'version' => 1,
                ]);
            });

            return ['success' => true, 'line' => $lineNumber];
        } catch (\Throwable $e) {
            return $this->skippedRow($lineNumber, "Database error: {$e->getMessage()}");
        }
    }

    private function skippedRow(int $lineNumber, string $reason): array
    {
        return [
            'success' => false,
            'line' => $lineNumber,
            'reason' => $reason,
        ];
    }

    public function failed(\Throwable $exception): void
    {
        $importLog = ImportLog::withoutGlobalScopes()->find($this->importLogId);

        if ($importLog) {
            $importLog->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_message' => 'Job failed after all retries: ' . $exception->getMessage(),
            ]);
        }
    }
}
