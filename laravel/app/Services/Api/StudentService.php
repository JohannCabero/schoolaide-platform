<?php

namespace App\Services\Api;

use App\Http\Requests\StudentRequest;
use App\Http\Resources\StudentPagination;
use App\Http\Resources\StudentResource;
use App\Models\Student;
use App\Services\BaseService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

class StudentService extends BaseService
{
    public function __construct(private readonly Student $student) {}

    public function index(StudentRequest $request)
    {
        return $this->executeFunction(function () use ($request) {
            Gate::authorize('viewAny', Student::class);

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

            $perPage = min((int) $request->input('per_page', 15), 100);
            $students = $query->paginate($perPage);

            return new StudentPagination($students);
        });
    }

    public function show(int $id)
    {
        return $this->executeFunction(function () use ($id) {
            $tenantId = app('currentTenant')?->id;
            $student  = Cache::remember(
                "tenant:{$tenantId}:student:{$id}",
                3600,
                fn() => $this->student->with('user')->findOrFail($id)
            );
            Gate::authorize('view', $student);

            return new StudentResource($student);
        });
    }

    public function store(StudentRequest $request)
    {
        return $this->executeFunction(function () use ($request) {
            Gate::authorize('create', Student::class);

            $student = $this->student->create(
                array_merge($request->validated(), [
                    'tenant_id' => app('currentTenant')->id,
                ])
            );

            $this->code = 201;
            $this->invalidateCache($student->id);

            return new StudentResource($student);
        });
    }

    public function update(StudentRequest $request, int $id)
    {
        return $this->executeFunction(function () use ($request, $id) {
            $student = $this->student->findOrFail($id);
            Gate::authorize('update', $student);

            $student->update($request->validated());

            $this->invalidateCache($student->id);

            return new StudentResource($student->fresh());
        });
    }

    public function destroy(int $id)
    {
        return $this->executeFunction(function () use ($id) {
            $student = $this->student->findOrFail($id);
            Gate::authorize('delete', $student);

            $student->delete();
            $this->invalidateCache($id);
        });
    }

    public function invalidateCache(int $id)
    {
        $tenantId = app('currentTenant')?->id;

        Cache::forget("tenant:{$tenantId}:student:{$id}");
    }
}
