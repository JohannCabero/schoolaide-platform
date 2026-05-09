<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class ImportLogRequest extends BaseRequest
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
                    'per_page' => ['sometimes', 'integer',],
                ];
            case 'POST':
                return [
                    'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
                ];
        }
    }
}
