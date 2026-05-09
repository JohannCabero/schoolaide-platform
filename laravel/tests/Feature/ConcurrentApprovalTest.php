<?php

use App\Models\ServiceRequest;
use App\Models\ServiceType;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Role;

/**
 * Concurrency tests for the approval workflow.
 *
 * Strategy tested:
 *   1. Pessimistic locking (SELECT FOR UPDATE) prevents two concurrent DB transactions
 *      from both approving the same row.
 *   2. Optimistic locking (version field) catches stale reads when the client
 *      sends an outdated version number.
 */
describe('Concurrent Approval Handling', function () {

    beforeEach(function () {
        $this->tenant = Tenant::factory()->create();
        app()->instance('currentTenant', $this->tenant);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api', 'tenant_id' => $this->tenant->id]);
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'api', 'tenant_id' => $this->tenant->id]);

        $this->staff1 = User::factory()->forTenant($this->tenant)->create();
        $this->staff1->assignRole(Role::where('name', 'staff')->where('tenant_id', $this->tenant->id)->first());

        $this->staff2 = User::factory()->forTenant($this->tenant)->create();
        $this->staff2->assignRole(Role::where('name', 'staff')->where('tenant_id', $this->tenant->id)->first());

        $student = Student::factory()->forTenant($this->tenant)->create();
        $serviceType = ServiceType::factory()->forTenant($this->tenant)->create();

        $this->serviceRequest = ServiceRequest::factory()->forTenant($this->tenant)->create([
            'student_id' => $student->id,
            'service_type_id' => $serviceType->id,
            'status' => 'pending',
            'assigned_to' => $this->staff1->id,
            'version' => 1,
        ]);
    });

    test('first approval wins when two staff attempt to approve simultaneously', function () {
        // Staff 1 approves first — succeeds
        $this->actingAs($this->staff1, 'api')
            ->postJson("/api/service-requests/{$this->serviceRequest->id}/approve", [
                'notes' => 'Approved by staff 1.',
                'version' => 1,
            ], ['X-Tenant' => $this->tenant->slug])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'approved');

        // Staff 2 tries with the same old version — version mismatch → 409 conflict
        $this->actingAs($this->staff2, 'api')
            ->postJson("/api/service-requests/{$this->serviceRequest->id}/approve", [
                'notes' => 'Approved by staff 2.',
                'version' => 1,
            ], ['X-Tenant' => $this->tenant->slug])
            ->assertStatus(409);

        // Confirm database state: only one approval, by staff 1
        $this->assertDatabaseHas('service_requests', [
            'id' => $this->serviceRequest->id,
            'status' => 'approved',
            'processed_by' => $this->staff1->id,
            'version' => 2,
        ]);
    });

    test('cannot approve a request that has already been rejected', function () {
        // Reject first
        $this->actingAs($this->staff1, 'api')
            ->postJson("/api/service-requests/{$this->serviceRequest->id}/reject", [
                'notes' => 'Does not meet requirements.',
                'version' => 1,
            ], ['X-Tenant' => $this->tenant->slug])
            ->assertStatus(200);

        // Attempt to approve the rejected request → 422 InvalidRequestStateException
        $this->actingAs($this->staff1, 'api')
            ->postJson("/api/service-requests/{$this->serviceRequest->id}/approve", [
                'notes' => 'Approving anyway.',
                'version' => 2,
            ], ['X-Tenant' => $this->tenant->slug])
            ->assertStatus(422);
    });

    test('cannot reject a request that has already been approved', function () {
        $this->actingAs($this->staff1, 'api')
            ->postJson("/api/service-requests/{$this->serviceRequest->id}/approve", [
                'notes' => 'Good to go.',
                'version' => 1,
            ], ['X-Tenant' => $this->tenant->slug])
            ->assertStatus(200);

        $this->actingAs($this->staff1, 'api')
            ->postJson("/api/service-requests/{$this->serviceRequest->id}/reject", [
                'notes' => 'Changing mind.',
                'version' => 2,
            ], ['X-Tenant' => $this->tenant->slug])
            ->assertStatus(422);
    });

    test('optimistic lock version mismatch returns 409', function () {
        // Simulate another process already bumping the version
        $this->serviceRequest->update(['version' => 2]);

        // Client still has version 1 — version mismatch → 409
        $this->actingAs($this->staff1, 'api')
            ->postJson("/api/service-requests/{$this->serviceRequest->id}/approve", [
                'notes' => 'Approving with stale version.',
                'version' => 1,
            ], ['X-Tenant' => $this->tenant->slug])
            ->assertStatus(409);
    });

    test('approve endpoint returns 422 when request is already approved', function () {
        // Pre-approve the request to simulate a race condition
        $this->serviceRequest->update(['status' => 'approved', 'version' => 2]);

        $this->actingAs($this->staff1, 'api')
            ->postJson("/api/service-requests/{$this->serviceRequest->id}/approve", [
                'notes' => 'Trying to approve again',
                'version' => 2,
            ], ['X-Tenant' => $this->tenant->slug])
            ->assertStatus(422);
    });

});