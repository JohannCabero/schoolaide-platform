<?php

namespace App\Http\Controllers\Api;

use App\Services\Api\TenantService;
use App\Http\Controllers\Controller;
use App\Http\Requests\TenantRequest;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    public function __construct(private readonly TenantService $service) {}

    public function profile()
    {
        return $this->service->profile();
    }

    public function registerTenant(TenantRequest $request)
    {
        return $this->service->registerTenant($request);
    }

    public function update(TenantRequest $request)
    {
        return $this->service->update($request);
    }
}
