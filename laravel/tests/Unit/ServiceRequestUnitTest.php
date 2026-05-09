<?php

use App\Models\ServiceRequest;
use App\Models\ServiceType;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\ServiceRequestPolicy;
use App\Policies\UserPolicy;
use Spatie\Permission\Models\Role;

describe('ServiceRequest Model Unit Tests', function () {

    beforeEach(function () {
        $this->tenant = Tenant::factory()->create();
        app()->instance('currentTenant', $this->tenant);

        $this->student = Student::factory()->forTenant($this->tenant)->create();
        $this->serviceType = ServiceType::factory()->forTenant($this->tenant)->create();
    });

    test('isPending returns true for pending status', function () {
        $request = ServiceRequest::factory()->forTenant($this->tenant)->make(['status' => 'pending']);
        expect($request->isPending())->toBeTrue();
    });

    test('isApproved returns true for approved status', function () {
        $request = ServiceRequest::factory()->forTenant($this->tenant)->make(['status' => 'approved']);
        expect($request->isApproved())->toBeTrue();
    });

    test('isRejected returns true for rejected status', function () {
        $request = ServiceRequest::factory()->forTenant($this->tenant)->make(['status' => 'rejected']);
        expect($request->isRejected())->toBeTrue();
    });

    test('isVersionFresh returns true when version matches', function () {
        $request = ServiceRequest::factory()->forTenant($this->tenant)->make(['version' => 3]);
        expect($request->isVersionFresh(3))->toBeTrue();
        expect($request->isVersionFresh(2))->toBeFalse();
    });

    test('scopeByStatus filters correctly', function () {
        ServiceRequest::factory()->forTenant($this->tenant)->count(2)->create([
            'student_id' => $this->student->id,
            'service_type_id' => $this->serviceType->id,
            'status' => 'approved',
        ]);
        ServiceRequest::factory()->forTenant($this->tenant)->count(3)->create([
            'student_id' => $this->student->id,
            'service_type_id' => $this->serviceType->id,
            'status' => 'pending',
        ]);

        expect(ServiceRequest::byStatus('approved')->count())->toBe(2);
        expect(ServiceRequest::byStatus('pending')->count())->toBe(3);
    });

    test('scopeByDateRange filters by date range', function () {
        ServiceRequest::factory()->forTenant($this->tenant)->create([
            'student_id' => $this->student->id,
            'service_type_id' => $this->serviceType->id,
            'requested_date' => now()->subDays(10)->toDateString(),
        ]);
        ServiceRequest::factory()->forTenant($this->tenant)->create([
            'student_id' => $this->student->id,
            'service_type_id' => $this->serviceType->id,
            'requested_date' => now()->subDays(2)->toDateString(),
        ]);

        $count = ServiceRequest::byDateRange(
            now()->subDays(5)->toDateString(),
            now()->toDateString()
        )->count();

        expect($count)->toBe(1);
    });

    test('student relationship is loaded correctly', function () {
        $request = ServiceRequest::factory()->forTenant($this->tenant)->create([
            'student_id' => $this->student->id,
            'service_type_id' => $this->serviceType->id,
        ]);

        expect($request->student->id)->toBe($this->student->id);
    });

});

describe('ServiceRequestPolicy Unit Tests', function () {

    beforeEach(function () {
        $this->tenant = Tenant::factory()->create();
        app()->instance('currentTenant', $this->tenant);

        Role::firstOrCreate(['name' => 'admin',   'guard_name' => 'api', 'tenant_id' => $this->tenant->id]);
        Role::firstOrCreate(['name' => 'staff',   'guard_name' => 'api', 'tenant_id' => $this->tenant->id]);
        Role::firstOrCreate(['name' => 'student', 'guard_name' => 'api', 'tenant_id' => $this->tenant->id]);

        $this->admin = User::factory()->forTenant($this->tenant)->create();
        $this->staff = User::factory()->forTenant($this->tenant)->create();
        $this->studentUser = User::factory()->forTenant($this->tenant)->create();

        $this->admin->assignRole(Role::where('name', 'admin')->where('tenant_id', $this->tenant->id)->first());
        $this->staff->assignRole(Role::where('name', 'staff')->where('tenant_id', $this->tenant->id)->first());
        $this->studentUser->assignRole(Role::where('name', 'student')->where('tenant_id', $this->tenant->id)->first());

        $student = Student::factory()->forTenant($this->tenant)->create();
        $serviceType = ServiceType::factory()->forTenant($this->tenant)->create();
        $this->request = ServiceRequest::factory()->forTenant($this->tenant)->create([
            'student_id' => $student->id,
            'service_type_id' => $serviceType->id,
            'assigned_to' => $this->staff->id,
        ]);

        $this->policy = new ServiceRequestPolicy();
    });

    test('admin can approve any request', function () {
        expect($this->policy->approve($this->admin, $this->request))->toBeTrue();
    });

    test('assigned staff can approve their request', function () {
        expect($this->policy->approve($this->staff, $this->request))->toBeTrue();
    });

    test("unassigned staff cannot approve another staff's request", function () {
        $otherStaff = User::factory()->forTenant($this->tenant)->create();
        $otherStaff->assignRole(Role::where('name', 'staff')->where('tenant_id', $this->tenant->id)->first());

        expect($this->policy->approve($otherStaff, $this->request))->toBeFalse();
    });

    test('student cannot approve any request', function () {
        expect($this->policy->approve($this->studentUser, $this->request))->toBeFalse();
    });

    test('admin can delete requests', function () {
        expect($this->policy->delete($this->admin, $this->request))->toBeTrue();
    });

    test('staff cannot delete requests', function () {
        expect($this->policy->delete($this->staff, $this->request))->toBeFalse();
    });

    test('cross-tenant user is denied approval', function () {
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->forTenant($otherTenant)->create();

        expect($this->policy->approve($otherUser, $this->request))->toBeFalse();
    });

});

describe('UserPolicy - Privilege Escalation Prevention', function () {

    beforeEach(function () {
        $this->tenant = Tenant::factory()->create();
        app()->instance('currentTenant', $this->tenant);

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'api', 'tenant_id' => $this->tenant->id]);
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'api', 'tenant_id' => $this->tenant->id]);

        $this->admin = User::factory()->forTenant($this->tenant)->create();
        $this->staff = User::factory()->forTenant($this->tenant)->create();
        $this->target = User::factory()->forTenant($this->tenant)->create();

        $this->admin->assignRole(Role::where('name', 'admin')->where('tenant_id', $this->tenant->id)->first());
        $this->staff->assignRole(Role::where('name', 'staff')->where('tenant_id', $this->tenant->id)->first());

        $this->policy = new UserPolicy();
    });

    test('admin can assign any role', function () {
        expect($this->policy->assignRole($this->admin, $this->target, 'staff'))->toBeTrue();
        expect($this->policy->assignRole($this->admin, $this->target, 'admin'))->toBeTrue();
    });

    test('staff cannot assign any role', function () {
        expect($this->policy->assignRole($this->staff, $this->target, 'student'))->toBeFalse();
        expect($this->policy->assignRole($this->staff, $this->target, 'admin'))->toBeFalse();
    });

    test('admin cannot delete themselves', function () {
        expect($this->policy->delete($this->admin, $this->admin))->toBeFalse();
    });

    test('admin can delete other users', function () {
        expect($this->policy->delete($this->admin, $this->staff))->toBeTrue();
    });

});
