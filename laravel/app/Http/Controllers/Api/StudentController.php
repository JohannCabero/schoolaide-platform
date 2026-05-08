<?php

namespace App\Http\Controllers\Api;

use App\Api\Services\StudentService;
use App\Http\Controllers\Controller;
use App\Http\Requests\StudentRequest;

class StudentController extends Controller
{
    public function __construct(private readonly StudentService $service) {}

    public function index(StudentRequest $request)
    {
        return $this->service->index($request);
    }

    public function show(int $studentId)
    {
        return $this->service->show($studentId);
    }

    public function store(StudentRequest $request)
    {
        return $this->service->store($request);
    }

    public function update(StudentRequest $request, int $studentId)
    {
        return $this->service->update($request, $studentId);
    }
}
