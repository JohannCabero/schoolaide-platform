<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class TenantRequest extends BaseRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules()
    {
        switch ($this->method()) {
            case 'POST':
                return [
                    // Tenant details
                    'organization_name' => ['required', 'string',],
                    'slug' => [
                        'required',
                        'string',
                        'max:63', // DNS subdomain label limit
                        'regex:/^[a-z0-9\-]+$/', // lowercase alphanumeric + hyphens only
                        Rule::unique('tenants', 'slug'),
                    ],
                    'domain' => [
                        'nullable',
                        'string',
                        'max:255',
                        'regex:/^(?!https?:\/\/)([a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}$/',
                        Rule::unique('tenants', 'domain'),
                    ],

                    // First admin user
                    'admin_name' => ['required', 'filled', 'string'],
                    'admin_email' => ['required', 'filled', 'string', 'email'],
                    'admin_password' => ['required', 'string', 'min:8', 'confirmed'],
                ];
            case 'PATCH':
                return [
                    'organization_name' => ['required', 'string',],
                    'domain' => [
                        'nullable',
                        'string',
                        'max:255',
                        'regex:/^(?!https?:\/\/)([a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}$/',
                        Rule::unique('tenants', 'domain')->ignore(app('currentTenant')->id),
                    ],
                ];
        }
    }
}
