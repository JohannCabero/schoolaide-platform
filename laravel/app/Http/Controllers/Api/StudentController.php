<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StudentRequest;
use App\Services\Api\StudentService;

class StudentController extends Controller
{
    public function __construct(private readonly StudentService $service) {}

    public function index(StudentRequest $request)
    {
        return $this->service->index($request);
    }

    public function show(int $id)
    {
        return $this->service->show($id);
    }

    public function store(StudentRequest $request)
    {
        return $this->service->store($request);
    }

    public function update(StudentRequest $request, int $id)
    {
        return $this->service->update($request, $id);
    }
}