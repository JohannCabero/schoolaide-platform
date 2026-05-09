<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Log;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        try {
            $tenantId = app('currentTenant')?->id;
        } catch (\Throwable $e) {
            Log::warning('TenantScope: currentTenant not bound', [
                'model'  => get_class($model),
                'error'  => $e->getMessage(),
                'caller' => collect(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 8))
                    ->map(fn ($f) => ($f['class'] ?? '') . '::' . ($f['function'] ?? ''))
                    ->implode(' → '),
            ]);
            $builder->whereRaw('0 = 1');

            return;
        }

        if ($tenantId !== null) {
            $builder->where($model->getTable() . '.tenant_id', $tenantId);
        } else {
            $builder->whereRaw('0 = 1');
        }
    }
}
