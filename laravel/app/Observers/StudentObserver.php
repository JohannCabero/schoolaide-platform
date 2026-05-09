<?php

namespace App\Observers;

use App\Models\Student;
use App\Services\Api\AuditService;

class StudentObserver
{
    public function __construct(private readonly AuditService $auditService) {}

    public function created(Student $model): void
    {
        $this->auditService->log('created', $model, null, $model->toArray(), auth('api')->id());
    }

    public function updated(Student $model): void
    {
        $this->auditService->log('updated', $model, $model->getOriginal(), $model->toArray(), auth('api')->id());
    }

    public function deleted(Student $model): void
    {
        $this->auditService->log('deleted', $model, $model->toArray(), null, auth('api')->id());
    }
}
