<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AuthRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules()
    {
        switch ($this->path()) {
            case 'api/register':
                return [
                    'name' => ['required', 'string',],
                    'email' => ['required', 'email', Rule::unique('students', 'email')->where('tenant_id', app('currentTenant')->id),],
                    'password' => ['required', 'string', 'min:8', 'confirmed'],
                    'role' => ['nullable', 'string', 'in:student,staff'],
                ];
            case 'api/login':
                return [
                    'email' => ['required', 'email',],
                    'password' => ['required', 'string',],
                ];
        }
    }
}
