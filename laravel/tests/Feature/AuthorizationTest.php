<?php

use App\Models\ServiceRequest;
use App\Models\ServiceType;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Role;

describe('Authorization - Role Based Access Control', function () {

    beforeEach(function () {
        $this->tenant = Tenant::factory()->create();
        app()->instance('currentTenant', $this->tenant);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api', 'tenant_id' => $this->tenant->id]);
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'api', 'tenant_id' => $this->tenant->id]);
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'api', 'tenant_id' => $this->tenant->id]);

        $this->admin = User::factory()->forTenant($this->tenant)->create();
        $this->staff = User::factory()->forTenant($this->tenant)->create();
        $this->studentUser = User::factory()->forTenant($this->tenant)->create();

        $this->admin->assignRole(Role::where('name', 'admin')->where('tenant_id', $this->tenant->id)->first());
        $this->staff->assignRole(Role::where('name', 'staff')->where('tenant_id', $this->tenant->id)->first());
        $this->studentUser->assignRole(Role::where('name', 'student')->where('tenant_id', $this->tenant->id)->first());

        $this->student = Student::factory()->forTenant($this->tenant)->create();
        $this->serviceType = ServiceType::factory()->forTenant($this->tenant)->create();

        $this->request = ServiceRequest::factory()->forTenant($this->tenant)->create([
            'student_id' => $this->student->id,
            'service_type_id' => $this->serviceType->id,
            'status' => 'pending',
            'assigned_to' => $this->staff->id,
        ]);
    });

    // ─── Admin access ─────────────────────────────────────────────────────────

    test('admin can list all service requests', function () {
        $this->actingAs($this->admin, 'api')
            ->getJson('/api/service-requests', ['X-Tenant' => $this->tenant->slug])
            ->assertStatus(200);
    });

    test('admin can approve any service request', function () {
        $this->actingAs($this->admin, 'api')
            ->patchJson("/api/service-requests/{$this->request->id}/approve", [
                'notes' => 'Approved by admin.',
                'version' => 1,
            ], ['X-Tenant' => $this->tenant->slug])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'approved');
    });

    test('admin can delete a service request', function () {
        $this->actingAs($this->admin, 'api')
            ->deleteJson("/api/service-requests/{$this->request->id}", [], [
                'X-Tenant' => $this->tenant->slug,
            ])
            ->assertStatus(200);

        $this->assertSoftDeleted('service_requests', ['id' => $this->request->id]);
    });

    // ─── Staff access ─────────────────────────────────────────────────────────

    test('staff can approve only requests assigned to them', function () {
        $this->actingAs($this->staff, 'api')
            ->patchJson("/api/service-requests/{$this->request->id}/approve", [
                'notes' => 'All good.',
                'version' => 1,
            ], ['X-Tenant' => $this->tenant->slug])
            ->assertStatus(200);
    });

    test('only assigned staff can approve a request — unassigned staff is denied', function () {
        $otherStaff = User::factory()->forTenant($this->tenant)->create();
        $otherStaff->assignRole(Role::where('name', 'staff')->where('tenant_id', $this->tenant->id)->first());

        $this->actingAs($otherStaff, 'api')
            ->patchJson("/api/service-requests/{$this->request->id}/approve", [
                'notes' => 'Trying to approve.',
                'version' => 1,
            ], ['X-Tenant' => $this->tenant->slug])
            ->assertStatus(403);
    });

    test('staff cannot delete a service request', function () {
        $this->actingAs($this->staff, 'api')
            ->deleteJson("/api/service-requests/{$this->request->id}", [], [
                'X-Tenant' => $this->tenant->slug,
            ])
            ->assertStatus(403);
    });

    // ─── Student access ───────────────────────────────────────────────────────

    test('student can only view their own service requests', function () {
        // Create a student linked to the student user
        $ownStudent = Student::factory()->forTenant($this->tenant)->create(['user_id' => $this->studentUser->id]);

        $ownRequest = ServiceRequest::factory()->forTenant($this->tenant)->create([
            'student_id' => $ownStudent->id,
            'service_type_id' => $this->serviceType->id,
        ]);

        // Student views their own request — success
        $this->actingAs($this->studentUser, 'api')
            ->getJson("/api/service-requests/{$ownRequest->id}", ['X-Tenant' => $this->tenant->slug])
            ->assertStatus(200);

        // Student views another student's request — denied
        $this->actingAs($this->studentUser, 'api')
            ->getJson("/api/service-requests/{$this->request->id}", ['X-Tenant' => $this->tenant->slug])
            ->assertStatus(403);
    });

    test('student cannot approve or reject a service request', function () {
        $this->actingAs($this->studentUser, 'api')
            ->patchJson("/api/service-requests/{$this->request->id}/approve", [
                'notes' => 'Self-approving!',
                'version' => 1,
            ], ['X-Tenant' => $this->tenant->slug])
            ->assertStatus(403);
    });

    // ─── Unauthenticated ─────────────────────────────────────────────────────

    test('unauthenticated request is rejected with 401', function () {
        $this->getJson('/api/service-requests', ['X-Tenant' => $this->tenant->slug])
            ->assertStatus(401);
    });

    // ─── Privilege Escalation ─────────────────────────────────────────────────

    test('staff cannot assign admin role to another user', function () {
        $newUser = User::factory()->forTenant($this->tenant)->create();

        // If a "assign role" endpoint exists, staff should be blocked
        // Here we verify the policy directly
        $policy = new \App\Policies\UserPolicy();

        expect($policy->assignRole($this->staff, $newUser, 'admin'))->toBeFalse();
        expect($policy->assignRole($this->admin, $newUser, 'admin'))->toBeTrue();
    });
});
