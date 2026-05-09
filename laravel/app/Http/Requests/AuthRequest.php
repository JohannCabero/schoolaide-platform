<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
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
            case 'api/auth/register':
                return [
                    'name' => ['required', 'string',],
                    'email' => ['required', 'email', Rule::unique('users', 'email')->where('tenant_id', app('currentTenant')->id),],
                    'password' => ['required', 'string', 'min:8', 'confirmed'],
                    'role' => ['nullable', 'string', 'in:student,staff'],
                ];
            case 'api/auth/login':
                return [
                    'email' => ['required', 'email',],
                    'password' => ['required', 'string',],
                ];
        }
    }
}
