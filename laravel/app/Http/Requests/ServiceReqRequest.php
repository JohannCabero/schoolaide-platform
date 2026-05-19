<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
                if ($this->is('api/service-requests/*/approve') || $this->is('api/service-requests/*/reject')) {
                    return [
                        'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
                        'version' => ['required', 'integer', 'min:1'],
                    ];
                }

                $tenantId = app('currentTenant')->id;

                return [
                    'student_number' => [
                        'required', 'string',
                        Rule::exists('students', 'student_number')->where('tenant_id', $tenantId),
                    ],
                    'service_type_code' => [
                        'required', 'string',
                        Rule::exists('service_types', 'code')->where('tenant_id', $tenantId),
                    ],
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
                    'requested_date' => ['sometimes', 'date', 'after_or_equal:today'],
                    'remarks' => ['sometimes', 'nullable', 'string', 'max:1000'],
                    'assigned_to' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
                    'version' => ['required', 'integer', 'min:1'],
                ];
        }
    }

    public function messages(): array
    {
        return [
            'student_number.exists' => 'The specified student number does not exist.',
            'service_type_code.exists' => 'The specified service type code does not exist.',
            'requested_date.after_or_equal' => 'The requested date must be today or a future date.',
        ];
    }
}
