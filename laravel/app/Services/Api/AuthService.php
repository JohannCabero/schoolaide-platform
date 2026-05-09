<?php

namespace App\Services\Api;

use App\Http\Requests\AuthRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\BaseService;
use App\Services\Api\AuditService;
use App\Traits\HasTenantPermissionScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class AuthService extends BaseService
{
    use HasTenantPermissionScope;

    public function __construct(private readonly AuditService $auditService) {}

    public function me(Request $request)
    {
        return $this->executeFunction(function () use ($request) {
            $user = $request->user();

            return new UserResource($user);
        });
    }

    public function registerUser(AuthRequest $request)
    {
        return $this->executeFunction(function () use ($request) {
            $tenant = app('currentTenant');

            $user = User::create([
                'tenant_id' => $tenant->id,
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);

            $roleName = $request->input('role', 'student');
            $this->setTenantPermissionScope($tenant->id);

            $role = Role::where('name', $roleName)
                ->where('guard_name', 'api')
                ->where('tenant_id', $tenant->id)
                ->first();

            if (! $role) {
                $role = Role::create([
                    'name' => $roleName,
                    'guard_name' => 'api',
                    'tenant_id' => $tenant->id,
                ]);
            }

            $user->assignRole($role);

            $this->auditService->log('created', $user, null, $user->toArray(), $user->id);

            $token = $user->createToken('api-token')->accessToken;

            $this->code = 201;

            return [
                'token' => $token,
                'user'  => new UserResource($user),
            ];
        });
    }

    public function login(AuthRequest $request)
    {
        $tenant = app('currentTenant');

        $user = User::where('email', $request->email)
            ->where('is_active', true)
            ->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'error' => ['The provided credentials are incorrect.'],
            ]);
        }

        $this->setTenantPermissionScope($tenant->id);
        $user->tokens()->delete();
        $token = $user->createToken('api-token')->accessToken;

        $this->auditService->log('login', $user, null, null, $user->id);

        return $this->normalizedResponse(200, 'Success', [
            'token' => $token,
            'user'  => new UserResource($user),
        ]);
    }

    public function logout(Request $request)
    {
        $user = $request->user();

        if ($user) {
            $user->tokens()->delete();
            $this->auditService->log('logout', $user, null, null, $user->id);
        }

        return response()->json(['message' => 'Logged out successfully.'], 200);
    }
}
