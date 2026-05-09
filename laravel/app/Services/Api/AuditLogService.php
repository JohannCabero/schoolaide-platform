<?php

namespace App\Services\Api;

use App\Http\Requests\AuditLogRequest;
use App\Http\Resources\AuditLogPagination;
use App\Models\AuditLog;
use App\Services\BaseService;
use Illuminate\Support\Facades\Gate;

class AuditLogService extends BaseService
{
    public function index(AuditLogRequest $request)
    {
        return $this->executeFunction(function () use ($request) {
            Gate::authorize('viewAny', AuditLog::class);

            $query = AuditLog::with('user')->orderBy('created_at', 'desc');

            if ($request->filled('action')) {
                $query->byAction($request->input('action'));
            }

            if ($request->filled('auditable_type')) {
                $query->where('auditable_type', 'like', '%' . $request->input('auditable_type') . '%');
            }

            if ($request->filled('auditable_id')) {
                $query->where('auditable_id', $request->integer('auditable_id'));
            }

            if ($request->filled('user_id')) {
                $query->where('user_id', $request->integer('user_id'));
            }

            if ($request->filled('date_from') || $request->filled('date_to')) {
                $query->byDateRange($request->input('date_from'), $request->input('date_to'));
            }

            $perPage = min($request->integer('per_page', 15), 100);

            return new AuditLogPagination($query->paginate($perPage));
        });
    }
}
