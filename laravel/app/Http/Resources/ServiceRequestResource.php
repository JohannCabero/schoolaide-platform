<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'requested_date' => $this->requested_date?->toDateString(),
            'remarks' => $this->remarks,
            'processing_notes' => $this->processing_notes,
            'processed_at' => $this->processed_at?->toIso8601String(),
            'version' => $this->version,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),

            'student' => $this->whenLoaded('student', [
                'id' => $this->student->id,
                'student_number' => $this->student->student_number,
                'full_name' => "{$this->student->first_name} {$this->student->last_name}",
                'email' => $this->student->email,
                'status' => $this->student->status,
            ]),

            'service_type' => $this->whenLoaded('serviceType', [
                'id' => $this->serviceType->id,
                'name' => $this->serviceType->name,
                'code' => $this->serviceType->code,
            ]),

            'assigned_to' => $this->whenLoaded('assignedTo', $this->assignedTo ? [
                'id' => $this->assignedTo->id,
                'name' => $this->assignedTo->name,
            ] : null),

            'processed_by' => $this->whenLoaded('processedBy', $this->processedBy ? [
                'id' => $this->processedBy->id,
                'name' => $this->processedBy->name,
            ] : null),
        ];
    }
}
