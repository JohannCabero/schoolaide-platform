<?php

namespace App\Services\Api;

use App\Http\Requests\TenantRequest;
use App\Http\Resources\TenantResource;
use App\Http\Resources\UserResource;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BaseService;
use App\Services\Api\AuditService;
use App\Traits\HasTenantPermissionScope;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class TenantService extends BaseService
{
    use HasTenantPermissionScope;

    public function __construct(private readonly AuditService $auditService) {}

    public function profile()
    {
        return $this->executeFunction(function () {
            $tenant = app('currentTenant');

            return new TenantResource($tenant);
        });
    }

    public function registerTenant(TenantRequest $request)
    {
        return $this->executeFunction(function () use ($request) {
            $tenant = Tenant::create([
                'name' => $request->organization_name,
                'slug' => $request->slug,
                ...($request->domain ? ['domain' => $request->domain] : []),
            ]);

            app()->instance('currentTenant', $tenant);
            $this->setTenantPermissionScope($tenant->id);

            $adminRole = Role::create(['name' => 'admin', 'guard_name' => 'api']);
            Role::create(['name' => 'staff', 'guard_name' => 'api']);
            Role::create(['name' => 'student', 'guard_name' => 'api']);

            $admin = User::create([
                'tenant_id' => $tenant->id,
                'name' => $request->admin_name,
                'email' => $request->admin_email,
                'password' => Hash::make($request->admin_password),
            ]);

            $admin->assignRole($adminRole);

            $adminId = $admin->id;
            $this->auditService->log('created', $tenant, null, $tenant->toArray(), $adminId);
            $this->auditService->log('created', $admin, null, $admin->toArray(), $adminId);

            $this->code = 201;
            $token = $admin->createToken('api-token')->accessToken;

            return [
                'tenant' => [
                    'id'     => $tenant->id,
                    'name'   => $tenant->name,
                    'slug'   => $tenant->slug,
                    'domain' => $tenant->domain,
                    'login_hint' => $tenant->domain
                        ? "Requests to {$tenant->domain} are automatically routed to this tenant. You can also use X-Tenant: {$tenant->slug}."
                        : "Use header X-Tenant: {$tenant->slug} for all subsequent requests.",
                ],
                'admin' => new UserResource($admin),
                'token' => $token,
            ];
        });
    }

    public function update(TenantRequest $request)
    {
        return $this->executeFunction(function () use ($request) {
            $tenant = app('currentTenant');
            $oldTenant = $tenant->toArray();
            $userId = auth('api')->id();

            $tenant->update($request->toArray());

            $this->auditService->log('updated', $tenant, $oldTenant, $tenant->fresh()->toArray(), $userId);

            return new TenantResource($tenant->fresh());
        });
    }
}
