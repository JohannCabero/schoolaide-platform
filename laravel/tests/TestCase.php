<?php

namespace Tests;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\Models\Role;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        app(\Laravel\Passport\ClientRepository::class)->createPersonalAccessGrantClient('Testing Personal Access Client');
    }

    /**
     * Set up a tenant and bind it into the container for the duration of the test.
     */
    protected function setupTenant(?Tenant $tenant = null): Tenant
    {
        $tenant ??= Tenant::factory()->create();
        app()->instance('currentTenant', $tenant);

        return $tenant;
    }

    /**
     * Create a user with the given role within the tenant.
     */
    protected function createUserWithRole(Tenant $tenant, string $roleName): User
    {
        $role = Role::firstOrCreate([
            'name'       => $roleName,
            'guard_name' => 'api',
            'tenant_id'  => $tenant->id,
        ]);

        $user = User::factory()->forTenant($tenant)->create();
        $user->assignRole($role);

        return $user;
    }

    /**
     * Act as the given user with the API guard (Passport).
     */
    protected function actingAsUser(User $user): static
    {
        return $this->actingAs($user, 'api');
    }

    /**
     * Provide the X-Tenant header for the tenant's slug.
     */
    protected function withTenantHeader(Tenant $tenant): array
    {
        return ['X-Tenant' => $tenant->slug];
    }
}
