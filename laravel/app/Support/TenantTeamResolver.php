<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Contracts\PermissionsTeamResolver;

class TenantTeamResolver implements PermissionsTeamResolver
{
    public function getPermissionsTeamId(): int|string|null
    {
        return app('currentTenant')?->id;
    }

    public function setPermissionsTeamId($id): void
    {
        // No-op: team ID is always resolved from the bound currentTenant.
    }
}