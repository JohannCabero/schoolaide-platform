<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class StudentPagination extends ResourceCollection
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'currrent_page' => $this->currentPage(),
            'data' => StudentResource::collection($this->collection),
            'first_page_url' => $this->url(1),
            'from' => ($this->currentPage() - 1) * $this->perPage() + 1,
            'last_page' => $this->lastPage(),
            'last_page_url' => $this->url($this->lastPage()),
            'links' => $this->links(),
            'next_page_url' => $this->nextPageUrl(),
            'path' => $this->path(),
            'per_page' => $this->perPage(),
            'prev_page_url' => $this->previousPageUrl(),
            'to' => min($this->currentPage() * $this->perPage(), $this->total()),
            'total_items' => $this->total(),
            'total_pages' => $this->lastPage(),
        ];
    }
}
