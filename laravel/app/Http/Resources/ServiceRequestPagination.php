<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ServiceRequestPagination extends ResourceCollection
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => ServiceRequestResource::collection($this->collection),
            'links' => [
                'first' => $this->url(1),
                'last'  => $this->url($this->lastPage()),
                'prev'  => $this->previousPageUrl(),
                'next'  => $this->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $this->currentPage(),
                'from'         => ($this->currentPage() - 1) * $this->perPage() + 1,
                'last_page'    => $this->lastPage(),
                'path'         => $this->path(),
                'per_page'     => $this->perPage(),
                'to'           => min($this->currentPage() * $this->perPage(), $this->total()),
                'total'        => $this->total(),
            ],
        ];
    }
}
