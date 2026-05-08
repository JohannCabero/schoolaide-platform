<?php

namespace App\Services\Api;

use App\Models\AuditLog;
use App\Services\BaseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

class AuditService extends BaseService
{
    public function log(string $action, Model $model, ?array $oldValues, ?array $newValues, ?int $userId = null)
    {
        try {
            $tenantId = app('currentTenant')?->id ?? $model->tenant_id ?? null;

            AuditLog::withoutGlobalScopes()->create([
                'tenant_id' => $tenantId,
                'user_id' => $userId ?? auth('api')->id(),
                'auditable_type' => get_class($model),
                'auditable_id' => $model->getKey(),
                'action' => $action,
                'old_values' => $this->sanitize($oldValues),
                'new_values' => $this->sanitize($newValues),
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to write audit log', [
                'action' => $action,
                'model' => get_class($model),
                'model_id' => $model->getKey(),
                'exception' => $e->getMessage(),
            ]);
        }
    }

    private function sanitize(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $sensitiveKeys = ['password', 'remember_token', 'api_token', 'token'];

        return array_diff_key($values, array_flip($sensitiveKeys));
    }
}
