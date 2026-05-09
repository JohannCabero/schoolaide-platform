<?php

namespace App\Services\Api;

use App\Exceptions\ConcurrencyConflictException;
use App\Exceptions\InvalidRequestStateException;
use App\Http\Requests\ServiceReqRequest;
use App\Http\Resources\ServiceRequestPagination;
use App\Http\Resources\ServiceRequestResource;
use App\Models\ServiceRequest;
use App\Services\BaseService;
use Illuminate\Support\Facades\Gate;

class ServiceRequestService extends BaseService
{
    public function __construct(private readonly ServiceRequest $serviceRequest, private readonly AuditService $auditService) {}

    public function index(ServiceReqRequest $request)
    {
        return $this->executeFunction(function () use ($request) {
            Gate::authorize('viewAny', ServiceRequest::class);

            $query = $this->serviceRequest->with(['student', 'serviceType', 'assignedTo', 'processedBy'])
                ->byDateRange($request->input('date_from'), $request->input('date_to'));

            if ($request->filled('status')) {
                $query->byStatus($request->input('status'));
            }

            if ($request->user()->isStudent()) {
                $student = $request->user()->student;
                $query->where('student_id', $student?->id);
            }

            if ($request->filled('assigned_to')) {
                $query->assignedTo((int) $request->input('assigned_to'));
            }

            $sortField = $request->input('sort_by', 'created_at');
            $sortDirection = $request->input('sort_dir', 'desc');

            $query->orderBy($sortField, $sortDirection);

            $perPage = min((int) $request->input('per_page', 15), 100);
            $serviceRequests = $query->paginate($perPage);

            return new ServiceRequestPagination($serviceRequests);
        });
    }

    public function show(int $id)
    {
        return $this->executeFunction(function () use ($id) {
            $serviceRequest = $this->serviceRequest->findOrFail($id);
            Gate::authorize('view', $serviceRequest);

            return new ServiceRequestResource($serviceRequest->load(['student', 'serviceType', 'assignedTo', 'processedBy']));
        });
    }

    public function store(ServiceReqRequest $request)
    {
        return $this->executeFunction(function () use ($request) {
            Gate::authorize('create', ServiceRequest::class);

            $tenantId = app('currentTenant')->id;
            $userId = auth('api')->id();

            $serviceReq = $this->serviceRequest->create(
                array_merge($request->validated(), ['tenant_id' => $tenantId])
            );

            $this->auditService->log('created', $serviceReq, null, $serviceReq->toArray(), $userId);

            return new ServiceRequestResource($serviceReq->load(['student', 'serviceType']));
        });
    }

    public function update(ServiceReqRequest $request, int $id)
    {
        return $this->executeFunction(function () use ($request, $id) {
            $userId = auth('api')->id();
            $lockedServiceReq = $this->serviceRequest->lockForUpdate()->findOrFail($id);
            Gate::authorize('update', $lockedServiceReq);

            if (! $lockedServiceReq->isPending()) {
                throw new InvalidRequestStateException(
                    "Cannot update a request that has already been {$lockedServiceReq->status}."
                );
            }

            $oldServiceReq = $lockedServiceReq->toArray();

            $lockedServiceReq->update(array_merge($request->validated(), ['version' => $lockedServiceReq->version + 1]));

            $this->auditService->log('updated', $lockedServiceReq, $oldServiceReq, $lockedServiceReq->toArray(), $userId);

            return new ServiceRequestResource($lockedServiceReq->fresh(['student', 'serviceType', 'assignedTo']));
        });
    }

    public function approve(ServiceReqRequest $request, int $id)
    {
        return $this->executeFunction(function () use ($request, $id) {
            $userId = auth('api')->id();
            $lockedServiceReq = $this->serviceRequest->lockForUpdate()->findOrFail($id);
            Gate::authorize('approve', $lockedServiceReq);

            if ($lockedServiceReq->version !== $request->version) {
                throw new ConcurrencyConflictException(
                    'The request was modified since you last viewed it. Please refresh and try again.'
                );
            }

            if (! $lockedServiceReq->isPending()) {
                throw new InvalidRequestStateException(
                    "Cannot approve a request with status '{$lockedServiceReq->status}'."
                );
            }

            $oldServiceReq = $lockedServiceReq->toArray();

            $lockedServiceReq->update([
                'status' => 'approved',
                ...($request->notes ? ['processing_notes' => $request->notes] : []),
                'processed_by' => $userId,
                'processed_at' => now(),
                'version' => $lockedServiceReq->version + 1,
            ]);

            $this->auditService->log('approved', $lockedServiceReq, $oldServiceReq, $lockedServiceReq->toArray(), $userId);

            return new ServiceRequestResource($lockedServiceReq->fresh(['student', 'serviceType', 'assignedTo', 'processedBy']));
        });
    }

    public function reject(ServiceReqRequest $request, int $id)
    {
        return $this->executeFunction(function () use ($request, $id) {
            $userId = auth('api')->id();
            $lockedServiceReq = $this->serviceRequest->lockForUpdate()->findOrFail($id);
            Gate::authorize('reject', $lockedServiceReq);

            if ($lockedServiceReq->version !== $request->version) {
                throw new ConcurrencyConflictException(
                    'The request was modified since you last viewed it. Please refresh and try again.'
                );
            }

            if (! $lockedServiceReq->isPending()) {
                throw new InvalidRequestStateException(
                    "Cannot reject a request with status '{$lockedServiceReq->status}'."
                );
            }

            $oldServiceReq = $lockedServiceReq->toArray();

            $lockedServiceReq->update([
                'status' => 'rejected',
                ...($request->notes ? ['processing_notes' => $request->notes] : []),
                'processed_by' => $userId,
                'processed_at' => now(),
                'version' => $lockedServiceReq->version + 1,
            ]);

            $this->auditService->log('rejected', $lockedServiceReq, $oldServiceReq, $lockedServiceReq->toArray(), $userId);

            return new ServiceRequestResource($lockedServiceReq->fresh(['student', 'serviceType', 'assignedTo', 'processedBy']));
        });
    }

    /**
     * Soft-delete a service request (admin only).
     */
    public function destroy(int $id)
    {
        return $this->executeFunction(function () use ($id) {
            $userId = auth('api')->id();
            $serviceReq = $this->serviceRequest->findOrFail($id);
            Gate::authorize('delete', $serviceReq);

            $this->auditService->log('deleted', $serviceReq, $serviceReq->toArray(), null, $userId);

            $serviceReq->delete();
        });
    }
}