<?php

use App\Models\ServiceRequest;
use App\Models\ServiceType;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Role;

describe('Service Request API', function () {

    beforeEach(function () {
        $this->tenant = Tenant::factory()->create();
        app()->instance('currentTenant', $this->tenant);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api', 'tenant_id' => $this->tenant->id]);
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'api', 'tenant_id' => $this->tenant->id]);

        $this->admin = User::factory()->forTenant($this->tenant)->create();
        $this->admin->assignRole(Role::where('name', 'admin')->where('tenant_id', $this->tenant->id)->first());

        $this->staff = User::factory()->forTenant($this->tenant)->create();
        $this->staff->assignRole(Role::where('name', 'staff')->where('tenant_id', $this->tenant->id)->first());

        $this->student = Student::factory()->forTenant($this->tenant)->create();
        $this->serviceType = ServiceType::factory()->forTenant($this->tenant)->create();
    });

    test('admin can create a service request', function () {
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/service-requests', [
                'student_id' => $this->student->id,
                'service_type_id' => $this->serviceType->id,
                'requested_date' => now()->addDays(3)->toDateString(),
                'remarks' => 'Urgently needed.',
            ], ['X-Tenant' => $this->tenant->slug])
            ->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'status', 'student', 'service_type', 'version']]);
    });

    test('creates a service request with status pending by default', function () {
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/service-requests', [
                'student_id' => $this->student->id,
                'service_type_id' => $this->serviceType->id,
                'requested_date' => now()->addDays(5)->toDateString(),
            ], ['X-Tenant' => $this->tenant->slug])
            ->assertJsonPath('data.status', 'pending');
    });

    test('cannot create a duplicate service request', function () {
        $date = now()->addDays(3)->toDateString();

        ServiceRequest::factory()->forTenant($this->tenant)->create([
            'student_id' => $this->student->id,
            'service_type_id' => $this->serviceType->id,
            'requested_date' => $date,
        ]);

        $this->actingAs($this->admin, 'api')
            ->postJson('/api/service-requests', [
                'student_id' => $this->student->id,
                'service_type_id' => $this->serviceType->id,
                'requested_date' => $date,
            ], ['X-Tenant' => $this->tenant->slug])
            ->assertStatus(422);
    });

    test('list returns paginated results', function () {
        ServiceRequest::factory()->forTenant($this->tenant)
            ->count(5)
            ->create([
                'student_id' => $this->student->id,
                'service_type_id' => $this->serviceType->id,
            ]);

        $this->actingAs($this->admin, 'api')
            ->getJson('/api/service-requests?per_page=3', ['X-Tenant' => $this->tenant->slug])
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'meta', 'links'])
            ->assertJsonPath('meta.per_page', 3);
    });

    test('list supports filtering by status', function () {
        ServiceRequest::factory()->forTenant($this->tenant)->approved()->create([
            'student_id' => $this->student->id,
            'service_type_id' => $this->serviceType->id,
        ]);
        ServiceRequest::factory()->forTenant($this->tenant)->pending()->create([
            'student_id' => $this->student->id,
            'service_type_id' => $this->serviceType->id,
        ]);

        $response = $this->actingAs($this->admin, 'api')
            ->getJson('/api/service-requests?status=approved', ['X-Tenant' => $this->tenant->slug])
            ->assertStatus(200);

        $statuses = collect($response->json('data'))->pluck('status')->unique()->values()->toArray();
        expect($statuses)->toBe(['approved']);
    });

    test('validation fails when student_id is missing', function () {
        $this->actingAs($this->admin, 'api')
            ->postJson('/api/service-requests', [
                'service_type_id' => $this->serviceType->id,
                'requested_date' => now()->addDay()->toDateString(),
            ], ['X-Tenant' => $this->tenant->slug])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['student_id']);
    });

    test('response format matches API specification', function () {
        $request = ServiceRequest::factory()->forTenant($this->tenant)->create([
            'student_id' => $this->student->id,
            'service_type_id' => $this->serviceType->id,
        ]);

        $this->actingAs($this->admin, 'api')
            ->getJson("/api/service-requests/{$request->id}", ['X-Tenant' => $this->tenant->slug])
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id', 'status', 'requested_date', 'remarks',
                    'processing_notes', 'processed_at', 'version',
                    'created_at', 'updated_at',
                    'student' => ['id', 'student_number', 'full_name'],
                    'service_type' => ['id', 'name', 'code'],
                ],
            ]);
    });

});
