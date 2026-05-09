<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AuditLogRequest;
use App\Services\Api\AuditLogService;

class AuditLogController extends Controller
{
    public function __construct(private readonly AuditLogService $service) {}

    public function index(AuditLogRequest $request)
    {
        return $this->service->index($request);
    }
}
