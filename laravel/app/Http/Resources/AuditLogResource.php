<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuditLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'auditable_type' => class_basename($this->auditable_type),
            'auditable_id' => $this->auditable_id,
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'ip_address' => $this->ip_address,
            'created_at' => $this->created_at->toIso8601String(),
            'performed_by' => $this->whenLoaded('user', fn() => $this->user
                ? ['id' => $this->user->id, 'name' => $this->user->name]
                : null
            ),
        ];
    }
}
