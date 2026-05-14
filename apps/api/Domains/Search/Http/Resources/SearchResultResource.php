<?php

namespace Domains\Search\Http\Resources;

use Domains\Search\Data\SearchResultData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SearchResultData
 */
class SearchResultResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'type' => $this->type,
            'id' => $this->id,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'status' => $this->status,
            'href' => $this->href,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
