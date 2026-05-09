<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ServiceReqRequest extends BaseRequest
{
    public function rules()
    {
        switch ($this->method()) {
            case 'GET':
                return [
                    'status' => ['sometimes', 'string',],
                    'date_from' => ['sometimes', 'date',],
                    'date_to' => ['sometimes', 'date',],
                    'assigned_to' => ['sometimes', 'integer', 'exists:users,id'],
                    'sort_by' => ['sometimes', 'string', 'in:created_at,requested_date,status'],
                    'sort_dir' => ['sometimes', 'string', 'in:asc,desc'],
                    'per_page' => ['sometimes', 'integer',],
                ];
            case 'POST':
                return [
                    'student_id' => ['required', 'integer', 'exists:students,id'],
                    'service_type_id' => ['required', 'integer', 'exists:service_types,id'],
                    'requested_date' => ['required', 'date', 'after_or_equal:today'],
                    'remarks' => ['nullable', 'string', 'max:1000'],
                    'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
                ];
            case 'PUT':
                return [
                    'requested_date' => ['sometimes', 'date', 'after_or_equal:today'],
                    'remarks' => ['sometimes', 'nullable', 'string', 'max:1000'],
                    'assigned_to' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
                ];
            case 'PATCH':
                return [
                    'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
                    'version' => ['required', 'integer', 'min:1'],
                ];
        }
    }

    public function messages(): array
    {
        return [
            'student_id.exists' => 'The specified student does not exist.',
            'service_type_id.exists' => 'The specified service type does not exist.',
            'requested_date.after_or_equal' => 'The requested date must be today or a future date.',
        ];
    }
}
