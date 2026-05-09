<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportLogRequest;
use App\Services\Api\ImportLogService;

class ImportLogController extends Controller
{
    public function __construct(private readonly ImportLogService $service) {}

    public function index(ImportLogRequest $request)
    {
        return $this->service->index($request);
    }

    public function show(int $id)
    {
        return $this->service->show($id);
    }

    public function store(ImportLogRequest $request)
    {
        return $this->service->store($request);
    }
}
