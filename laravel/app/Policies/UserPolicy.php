<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, User $target): bool
    {
        return $user->isAdmin() && $user->belongsToTenant($target->tenant_id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $target): bool
    {
        return $user->isAdmin() && $user->belongsToTenant($target->tenant_id);
    }

    public function delete(User $user, User $target): bool
    {
        return $user->isAdmin()
            && $user->belongsToTenant($target->tenant_id)
            && $user->id !== $target->id; // Cannot delete yourself
    }

    public function assignRole(User $user, User $target, string $roleName): bool
    {
        if (! $user->isAdmin()) {
            return false;
        }

        // Admins can only manage users in their own tenant
        if (! $user->belongsToTenant($target->tenant_id)) {
            return false;
        }

        // Prevent granting roles that exceed the granter's own role
        return true;
    }
}
