<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;

class BaseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function failedValidation(Validator $validator)
    {
        $error = $validator->errors()->toArray();
        $payload = request()->except(['password']);

        Log::error('Validation Failed', [
            'input_data' => $payload,
            'trace' => $error,
        ]);

        parent::failedValidation($validator);
    }
}
