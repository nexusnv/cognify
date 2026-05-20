<?php

namespace Domains\Vendor\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendorPickerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'category' => $this->category,
            'status' => $this->status,
            'riskRating' => $this->risk_rating,
            'defaultContact' => [
                'name' => data_get($this->metadata, 'contactName'),
                'email' => data_get($this->metadata, 'contactEmail'),
            ],
        ];
    }
}
