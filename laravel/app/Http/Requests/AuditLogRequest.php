<?php

namespace App\Http\Requests;

class AuditLogRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'action' => ['sometimes', 'string', 'in:created,updated,deleted,restored,approved,rejected'],
            'auditable_type' => ['sometimes', 'string'],
            'auditable_id' => ['sometimes', 'integer'],
            'user_id' => ['sometimes', 'integer'],
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date'],
            'per_page' => ['sometimes', 'integer'],
        ];
    }
}
