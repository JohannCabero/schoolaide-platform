<?php

namespace App\Policies;

use App\Models\ImportLog;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ImportLogPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isStaff();
    }

    public function view(User $user, ImportLog $importLog): bool
    {
        return $user->belongsToTenant($importLog->tenant_id)
            && ($user->isAdmin() || $user->isStaff() || $importLog->user_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isStaff();
    }
}
