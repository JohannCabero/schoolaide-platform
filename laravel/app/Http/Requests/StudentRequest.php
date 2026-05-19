<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class StudentRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules()
    {
        switch ($this->method()) {
            case 'GET':
                return [
                    'status' => ['sometimes', 'string',],
                    'search' => ['sometimes', 'string',],
                    'sort_by' => ['sometimes', 'string', 'in:created_at,last_name,student_number,status'],
                    'sort_dir' => ['sometimes', 'string', 'in:asc,desc'],
                    'per_page' => ['sometimes', 'integer',],
                ];
            case 'POST':
                return [
                    'student_number' => ['required', 'filled', 'string', Rule::unique('students', 'student_number')->where('tenant_id', app('currentTenant')->id),],
                    'first_name' => ['required', 'filled', 'string',],
                    'last_name' => ['required', 'filled', 'string',],
                    'email' => ['required', 'filled', 'string', 'email', Rule::unique('students', 'email')->where('tenant_id', app('currentTenant')->id)],
                    'phone' => ['sometimes', 'string', 'phone:PH',],
                    'birth_date' => ['sometimes', 'string', 'date', 'before:today',],
                    'program' => ['sometimes', 'string',],
                    'year_level' => ['sometimes', 'integer',],
                    'status' => ['sometimes', 'string', 'in:active,inactive,graduated,suspended',],
                ];
            case 'PATCH':
            case 'PUT':
                return [
                    'student_number' => ['sometimes', 'filled', 'string', Rule::unique('students', 'student_number')->where('tenant_id', app('currentTenant')->id),],
                    'first_name' => ['sometimes', 'filled', 'string',],
                    'last_name' => ['sometimes', 'filled', 'string',],
                    'email' => ['sometimes', 'filled', 'string', 'email', Rule::unique('students', 'email')->where('tenant_id', app('currentTenant')->id)],
                    'phone' => ['sometimes', 'string', 'phone:PH',],
                    'birth_date' => ['sometimes', 'string', 'date', 'before:today',],
                    'program' => ['sometimes', 'string',],
                    'year_level' => ['sometimes', 'string',],
                    'status' => ['sometimes', 'string', 'in:active,inactive,graduated,suspended',],
                ];
        }
    }
}
