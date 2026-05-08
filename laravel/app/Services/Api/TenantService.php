<?php

namespace App\Services\Api;

use App\Http\Requests\TenantRequest;
use App\Http\Resources\TenantResource;
use App\Http\Resources\UserResource;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BaseService;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class TenantService extends BaseService
{
    public function profile()
    {
        return $this->executeFunction(function () {
            $tenant = app('currentTenant');

            return new TenantResource($tenant);
        });
    }

    public function registerTenant(TenantRequest $request)
    {
        // return $this->executeFunction(function () use ($request) {
        $tenant = Tenant::create([
            'name' => $request->organization_name,
            'slug' => $request->slug,
            ...($request->domain ? ['domain' => $request->domain] : []),
        ]);

        app()->instance('currentTenant', $tenant);

        $adminRole = Role::create(['name' => 'admin', 'guard_name' => 'api', 'tenant_id' => $tenant->id]);
        $staffRole = Role::create(['name' => 'staff', 'guard_name' => 'api', 'tenant_id' => $tenant->id]);
        $studentRole = Role::create(['name' => 'student', 'guard_name' => 'api', 'tenant_id' => $tenant->id]);

        $admin = User::create([
            'tenant_id' => $tenant->id,
            'name' => $request->admin_name,
            'email' => $request->admin_email,
            'password' => Hash::make($request->admin_password),
        ]);

        $admin->assignRole($adminRole);

        $this->code = 201;
        $token = $admin->createToken('api-token')->accessToken;

        return [
            'tenant' => [
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'domain' => $tenant->domain,
                'login_hint' => $tenant->domain
                    ? "Requests to {$tenant->domain} are automatically routed to this tenant. You can also use 'X-Tenant: {$tenant->slug}'."
                    : "Use header 'X-Tenant: {$tenant->slug}' for all subsequent requests.",
            ],
            'admin' => new UserResource($admin),
            'token' => $token,
        ];
        // });
    }

    public function update(TenantRequest $request)
    {
        return $this->executeFunction(function () use ($request) {
            $tenant = app('currentTenant');

            $tenant->update($request->toArray());

            return new TenantResource($tenant->fresh());
        });
    }
}
