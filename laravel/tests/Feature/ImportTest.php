<?php

use App\Jobs\ProcessImportJob;
use App\Models\ImportLog;
use App\Models\ServiceRequest;
use App\Models\ServiceType;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Role;

describe('Data Import', function () {

    beforeEach(function () {
        Storage::fake('local');
        Queue::fake();

        $this->tenant = Tenant::factory()->create();
        app()->instance('currentTenant', $this->tenant);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api', 'tenant_id' => $this->tenant->id]);

        $this->admin = User::factory()->forTenant($this->tenant)->create();
        $this->admin->assignRole(
            Role::where('name', 'admin')->where('tenant_id', $this->tenant->id)->first()
        );

        $this->serviceType = ServiceType::factory()->forTenant($this->tenant)->create([
            'code' => 'TRANSCRIPT',
        ]);

        $this->activeStudent = Student::factory()->forTenant($this->tenant)->create([
            'student_number' => '2024-0001',
            'status' => 'active',
        ]);

        $this->inactiveStudent = Student::factory()->forTenant($this->tenant)->create([
            'student_number' => '2024-0002',
            'status' => 'inactive',
        ]);
    });

    test('uploading an Excel file creates an import log and dispatches a job', function () {
        $file = UploadedFile::fake()->create('requests.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->actingAs($this->admin, 'api')
            ->postJson('/api/imports', ['file' => $file], ['X-Tenant' => $this->tenant->slug])
            ->assertStatus(202)
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('import_logs', [
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->admin->id,
            'original_filename' => 'requests.xlsx',
            'status' => 'pending',
        ]);

        Queue::assertPushed(ProcessImportJob::class);
    });

    test('import skips rows with missing student number and logs the reason', function () {
        $importLog = ImportLog::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->admin->id,
            'filename' => 'imports/test.xlsx',
            'original_filename' => 'test.xlsx',
            'status' => 'pending',
        ]);

        // Test the job logic directly using a mock of the row processor
        $job = new ProcessImportJob($importLog->id, $this->tenant->id);

        // Use reflection to test the private processRow method
        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('processRow');
        $method->setAccessible(true);

        // Row with missing student number
        $result = $method->invoke($job, [
            'student number' => '',
            'service type' => 'TRANSCRIPT',
            'requested date' => now()->addDay()->toDateString(),
        ], $this->tenant->id, 2);

        expect($result['success'])->toBeFalse();
        expect($result['reason'])->toContain('Missing student number');
    });

    test('import skips rows where student does not exist', function () {
        $job = new ProcessImportJob(0, $this->tenant->id);

        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('processRow');
        $method->setAccessible(true);

        $result = $method->invoke($job, [
            'student number' => '9999-9999', // non-existent
            'service type' => 'TRANSCRIPT',
            'requested date' => now()->addDay()->toDateString(),
        ], $this->tenant->id, 3);

        expect($result['success'])->toBeFalse();
        expect($result['reason'])->toContain('not found');
    });

    test('import skips rows where student is inactive', function () {
        $job = new ProcessImportJob(0, $this->tenant->id);

        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('processRow');
        $method->setAccessible(true);

        $result = $method->invoke($job, [
            'student number' => '2024-0002', // inactive student
            'service type' => 'TRANSCRIPT',
            'requested date' => now()->addDay()->toDateString(),
        ], $this->tenant->id, 4);

        expect($result['success'])->toBeFalse();
        expect($result['reason'])->toContain('not active');
    });

    test('import skips rows with invalid service type', function () {
        $job = new ProcessImportJob(0, $this->tenant->id);

        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('processRow');
        $method->setAccessible(true);

        $result = $method->invoke($job, [
            'student number' => '2024-0001',
            'service type' => 'INVALID_TYPE',
            'requested date' => now()->addDay()->toDateString(),
        ], $this->tenant->id, 5);

        expect($result['success'])->toBeFalse();
        expect($result['reason'])->toContain('not valid');
    });

    test('import skips duplicate requests and logs reason', function () {
        $date = now()->addDays(2)->toDateString();

        // Pre-create the request to simulate a duplicate
        ServiceRequest::create([
            'tenant_id' => $this->tenant->id,
            'student_id' => $this->activeStudent->id,
            'service_type_id' => $this->serviceType->id,
            'requested_date' => $date,
            'status' => 'pending',
            'version' => 1,
        ]);

        $job = new ProcessImportJob(0, $this->tenant->id);

        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('processRow');
        $method->setAccessible(true);

        $result = $method->invoke($job, [
            'student number' => '2024-0001',
            'service type' => 'TRANSCRIPT',
            'requested date' => $date,
        ], $this->tenant->id, 6);

        expect($result['success'])->toBeFalse();
        expect($result['reason'])->toContain('Duplicate');
    });

    test('valid row creates a service request successfully', function () {
        $job = new ProcessImportJob(0, $this->tenant->id);

        $reflection = new \ReflectionClass($job);
        $method = $reflection->getMethod('processRow');
        $method->setAccessible(true);

        $result = $method->invoke($job, [
            'student number' => '2024-0001',
            'service type' => 'TRANSCRIPT',
            'requested date' => now()->addDays(7)->toDateString(),
        ], $this->tenant->id, 2);

        expect($result['success'])->toBeTrue();

        $this->assertDatabaseHas('service_requests', [
            'tenant_id' => $this->tenant->id,
            'student_id' => $this->activeStudent->id,
            'status' => 'pending',
        ]);
    });

    test('non-admin cannot upload imports', function () {
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'api', 'tenant_id' => $this->tenant->id]);
        $student = User::factory()->forTenant($this->tenant)->create();
        $student->assignRole(Role::where('name', 'student')->where('tenant_id', $this->tenant->id)->first());

        $file = UploadedFile::fake()->create('requests.xlsx', 100);

        $this->actingAs($student, 'api')
            ->postJson('/api/imports', ['file' => $file], ['X-Tenant' => $this->tenant->slug])
            ->assertStatus(403);
    });

    test('upload rejects non-excel files', function () {
        $file = UploadedFile::fake()->create('requests.pdf', 100, 'application/pdf');

        $this->actingAs($this->admin, 'api')
            ->postJson('/api/imports', ['file' => $file], ['X-Tenant' => $this->tenant->slug])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    });

    test('import list is paginated and scoped to tenant', function () {
        ImportLog::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->admin->id,
        ]);

        $this->actingAs($this->admin, 'api')
            ->getJson('/api/imports?per_page=3', ['X-Tenant' => $this->tenant->slug])
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'meta', 'links'])
            ->assertJsonPath('meta.per_page', 3);
    });

});
