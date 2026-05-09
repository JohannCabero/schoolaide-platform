<?php

use App\Models\ServiceRequest;
use App\Models\ServiceType;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Role;

/**
 * CRITICAL: Multi-tenant isolation tests.
 *
 * These verify that the global TenantScope prevents cross-tenant data access
 * at the API level. A user from Tenant A must NEVER be able to read, modify,
 * or delete Tenant B's data — even with a valid Passport token.
 */
describe('Multi-Tenant Isolation', function () {

    beforeEach(function () {
        // Tenant A setup
        $this->tenantA = Tenant::factory()->create(['slug' => 'tenant-a']);
        app()->instance('currentTenant', $this->tenantA);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api', 'tenant_id' => $this->tenantA->id]);
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'api', 'tenant_id' => $this->tenantA->id]);

        $this->adminA = User::factory()->forTenant($this->tenantA)->create();
        $this->adminA->assignRole(Role::where('name', 'admin')->where('tenant_id', $this->tenantA->id)->first());

        $this->studentA = Student::factory()->forTenant($this->tenantA)->create();
        $this->serviceTypeA = ServiceType::factory()->forTenant($this->tenantA)->create();

        $this->requestA = ServiceRequest::factory()->forTenant($this->tenantA)->create([
            'student_id' => $this->studentA->id,
            'service_type_id' => $this->serviceTypeA->id,
        ]);

        // Tenant B setup
        $this->tenantB = Tenant::factory()->create(['slug' => 'tenant-b']);
        app()->instance('currentTenant', $this->tenantB);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api', 'tenant_id' => $this->tenantB->id]);

        $this->adminB = User::factory()->forTenant($this->tenantB)->create();
        $this->adminB->assignRole(Role::where('name', 'admin')->where('tenant_id', $this->tenantB->id)->first());

        $this->studentB = Student::factory()->forTenant($this->tenantB)->create();
        $this->serviceTypeB = ServiceType::factory()->forTenant($this->tenantB)->create();

        $this->requestB = ServiceRequest::factory()->forTenant($this->tenantB)->create([
            'student_id' => $this->studentB->id,
            'service_type_id' => $this->serviceTypeB->id,
        ]);
    });

    test('staff from tenant A cannot view service requests from tenant B', function () {
        // Set context to Tenant A (simulates middleware)
        app()->instance('currentTenant', $this->tenantA);

        $response = $this->actingAs($this->adminA, 'api')
            ->getJson("/api/service-requests/{$this->requestB->id}", [
                'X-Tenant' => $this->tenantA->slug,
            ]);

        $response->assertStatus(404);
    });

    test('admin from tenant A cannot list students from tenant B', function () {
        app()->instance('currentTenant', $this->tenantA);

        $response = $this->actingAs($this->adminA, 'api')
            ->getJson('/api/students', ['X-Tenant' => $this->tenantA->slug]);

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('id')->toArray();

        // Must not contain Tenant B's student
        expect($ids)->not->toContain($this->studentB->id);
        expect($ids)->toContain($this->studentA->id);
    });

    test('admin from tenant B cannot update a request belonging to tenant A', function () {
        app()->instance('currentTenant', $this->tenantB);

        $response = $this->actingAs($this->adminB, 'api')
            ->patchJson("/api/service-requests/{$this->requestA->id}", [
                'remarks' => 'Hacked!',
                'version' => 1,
            ], ['X-Tenant' => $this->tenantB->slug]);

        $response->assertStatus(404);
    });

    test('admin from tenant B cannot delete a student from tenant A', function () {
        app()->instance('currentTenant', $this->tenantB);

        $response = $this->actingAs($this->adminB, 'api')
            ->deleteJson("/api/students/{$this->studentA->id}", [], [
                'X-Tenant' => $this->tenantB->slug,
            ]);

        $response->assertStatus(404);
        $this->assertDatabaseHas('students', ['id' => $this->studentA->id, 'deleted_at' => null]);
    });

    test('using a valid token from tenant A against tenant B endpoint returns 403', function () {
        // Tenant B context is set (different from user's tenant)
        app()->instance('currentTenant', $this->tenantB);

        $response = $this->actingAs($this->adminA, 'api')
            ->getJson('/api/students', ['X-Tenant' => $this->tenantB->slug]);

        // User belongs to Tenant A but X-Tenant is Tenant B → 403
        $response->assertStatus(403);
    });

    test('global scope filters models correctly per tenant context', function () {
        app()->instance('currentTenant', $this->tenantA);

        $students = Student::all();

        expect($students->pluck('tenant_id')->unique()->toArray())
            ->toBe([$this->tenantA->id]);
    });

});
