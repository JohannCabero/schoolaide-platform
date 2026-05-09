<?php

namespace App\Observers;

use App\Models\ServiceRequest;
use App\Services\Api\AuditService;

class ServiceRequestObserver
{
    public function __construct(private readonly AuditService $auditService) {}

    public function created(ServiceRequest $model): void
    {
        $this->auditService->log('created', $model, null, $model->toArray(), auth('api')->id());
    }

    public function updated(ServiceRequest $model): void
    {
        $action = match (true) {
            $model->wasChanged('status') && $model->status === 'approved' => 'approved',
            $model->wasChanged('status') && $model->status === 'rejected' => 'rejected',
            default => 'updated',
        };

        $this->auditService->log($action, $model, $model->getOriginal(), $model->toArray(), auth('api')->id());
    }

    public function deleted(ServiceRequest $model): void
    {
        $this->auditService->log('deleted', $model, $model->toArray(), null, auth('api')->id());
    }
}
