<?php

namespace App\Services\Api;

use App\Http\Requests\StudentRequest;
use App\Http\Resources\StudentPagination;
use App\Http\Resources\StudentResource;
use App\Models\Student;
use App\Services\BaseService;
use Illuminate\Support\Facades\Cache;

class StudentService extends BaseService
{
    private $student;


    public function __construct(Student $student)
    {
        $this->student = $student;
    }

    public function index(StudentRequest $request)
    {
        return $this->executeFunction(function () use ($request) {
            $query = Student::query();

            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }

            if ($request->filled('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('student_number', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            }

            $sortField = $request->input('sort_by', 'created_at');
            $sortDirection = $request->input('sort_dir', 'desc');

            $query->orderBy($sortField, $sortDirection);

            $perPage  = min((int) $request->input('per_page', 15), 100);
            $students = $query->paginate($perPage);

            return new StudentPagination($students);
        });
    }

    public function show(int $studentId)
    {
        return $this->executeFunction(function () use ($studentId) {
            $student = Cache::tags(["tenant:" . app('currentTenant')?->id . ":students"])
                ->remember(
                    "student:{$studentId}",
                    3600,
                    fn() => $this->student->with('user')->findOrFail($studentId)
                );

            return new StudentResource($student);
        });
    }

    public function store(StudentRequest $request)
    {
        return $this->executeFunction(function () use ($request) {
            $student = $this->student->create(
                array_merge($request->validated(), [
                    'tenant_id' => app('currentTenant')->id,
                    'user_id'   => $request->user()->id,
                ])
            );

            $this->code = 201;
            $this->invalidateCache($student->id);

            return new StudentResource($student);
        });
    }

    public function update(StudentRequest $request, int $studentId)
    {
        return $this->executeFunction(function () use ($request, $studentId) {
            $student = $this->student->findOrFail($studentId);

            $student->update($request->validated());

            $this->invalidateCache($student->id);

            return new StudentResource($student->fresh());
        });
    }

    public function invalidateCache(int $id)
    {
        $tenantId = app('currentTenant')?->id;

        Cache::tags(["tenant:{$tenantId}:students"])->forget("student:{$id}");
    }

    public function invalidateAllStudentCache()
    {
        $tenantId = app('currentTenant')?->id;
        Cache::tags(["tenant:{$tenantId}:students"])->flush();
    }
}
