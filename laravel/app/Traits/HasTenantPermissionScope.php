<?php

namespace App\Traits;

use Spatie\Permission\PermissionRegistrar;

trait HasTenantPermissionScope
{
    protected function setTenantPermissionScope(int $tenantId): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenantId);
    }

    protected function clearTenantPermissionScope(): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
    }
}
