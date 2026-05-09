<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceReqRequest;
use App\Services\Api\ServiceRequestService;

class ServiceRequestController extends Controller
{
    public function __construct(private readonly ServiceRequestService $service) {}

    public function index(ServiceReqRequest $request)
    {
        return $this->service->index($request);
    }

    public function show(int $id)
    {
        return $this->service->show($id);
    }

    public function store(ServiceReqRequest $request)
    {
        return $this->service->store($request);
    }

    public function update(ServiceReqRequest $request, int $id)
    {
        return $this->service->update($request, $id);
    }

    public function approve(ServiceReqRequest $request, int $id)
    {
        return $this->service->approve($request, $id);
    }

    public function reject(ServiceReqRequest $request, int $id)
    {
        return $this->service->reject($request, $id);
    }

    public function destroy(int $id)
    {
        return $this->service->destroy($id);
    }
}