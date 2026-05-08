<?php

namespace App\Api\Services;

use App\Http\Requests\AuthRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\BaseService;
use App\Traits\HasTenantPermissionScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class AuthService extends BaseService
{
    use HasTenantPermissionScope;

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
        return $this->executeFunction(function () use ($request) {
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

            return [
                'token' => $token,
                'user'  => new UserResource($user),
            ];
        });
    }

    public function logout(Request $request)
    {
        return $this->executeFunction(function () use ($request) {
            $request->user()->token()->revoke();

            return 'Logged out successfully.';
        });
    }
}
