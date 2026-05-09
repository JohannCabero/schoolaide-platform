<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/
expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions (helpers available in all tests)
|--------------------------------------------------------------------------
*/
function createTenantWithRoles(): array
{
    $tenant = \App\Models\Tenant::factory()->create();
    app()->instance('currentTenant', $tenant);

    $adminRole   = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin',   'guard_name' => 'api', 'tenant_id' => $tenant->id]);
    $staffRole   = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'staff',   'guard_name' => 'api', 'tenant_id' => $tenant->id]);
    $studentRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'student', 'guard_name' => 'api', 'tenant_id' => $tenant->id]);

    $admin   = \App\Models\User::factory()->forTenant($tenant)->create();
    $staff   = \App\Models\User::factory()->forTenant($tenant)->create();
    $student = \App\Models\User::factory()->forTenant($tenant)->create();

    $admin->assignRole($adminRole);
    $staff->assignRole($staffRole);
    $student->assignRole($studentRole);

    return compact('tenant', 'admin', 'staff', 'student');
}
