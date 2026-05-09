<?php

namespace App\Services\Api;

use App\Http\Requests\ImportLogRequest;
use App\Http\Resources\ImportLogPagination;
use App\Http\Resources\ImportLogResource;
use App\Jobs\ProcessImportJob;
use App\Models\ImportLog;
use App\Services\BaseService;
use Illuminate\Support\Facades\Gate;

class ImportLogService extends BaseService
{
    public function __construct(private readonly ImportLog $importLog) {}

    public function index(ImportLogRequest $request)
    {
        return $this->executeFunction(function () use ($request) {
            Gate::authorize('viewAny', ImportLog::class);

            $perPage = min((int) $request->input('per_page', 15), 100);

            $importLogs = $this->importLog->with('user')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return new ImportLogPagination($importLogs);
        });
    }

    public function show(int $id)
    {
        return $this->executeFunction(function () use ($id) {
            $importLog = $this->importLog->findOrFail($id);

            Gate::authorize('view', $importLog);

            return new ImportLogResource($importLog->load('user'));
        });
    }

    public function store(ImportLogRequest $request)
    {
        return $this->executeFunction(function () use ($request) {
            Gate::authorize('create', ImportLog::class);

            $tenantId = app('currentTenant')->id;
            $userId = auth('api')->id();

            $path = $request->file('file')->store("imports/{$tenantId}");
            $originalFilename = $request->file('file')->getClientOriginalName();

            $importLog = ImportLog::create([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'filename' => $path,
                'original_filename' => $originalFilename,
                'status' => 'pending',
            ]);

            ProcessImportJob::dispatch($importLog->id, $tenantId)->onQueue('imports');

            $this->code = 202;

            return new ImportLogResource($importLog);
        });
    }
}
