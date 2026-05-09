<?php

namespace App\Policies;

use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ServiceRequestPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isStaff() || $user->isStudent();
    }

    public function view(User $user, ServiceRequest $request): bool
    {
        if (! $user->belongsToTenant($request->tenant_id)) {
            return false;
        }

        if ($user->isAdmin() || $user->isStaff()) {
            return true;
        }

        // Students may only view their own requests
        if ($user->isStudent()) {
            return $user->student?->id === $request->student_id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isStaff() || $user->isStudent();
    }

    public function update(User $user, ServiceRequest $request): bool
    {
        if (! $user->belongsToTenant($request->tenant_id)) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        // Staff may update only assigned requests that are still pending
        if ($user->isStaff()) {
            return $request->assigned_to === $user->id && $request->isPending();
        }

        return false;
    }

    public function delete(User $user, ServiceRequest $request): bool
    {
        return $user->isAdmin() && $user->belongsToTenant($request->tenant_id);
    }

    public function approve(User $user, ServiceRequest $request): bool
    {
        if (! $user->belongsToTenant($request->tenant_id)) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isStaff()) {
            return $request->assigned_to === $user->id;
        }

        return false;
    }

    public function reject(User $user, ServiceRequest $request): bool
    {
        return $this->approve($user, $request);
    }
}
