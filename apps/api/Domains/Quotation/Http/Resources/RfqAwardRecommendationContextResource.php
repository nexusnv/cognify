<?php

namespace Domains\Quotation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RfqAwardRecommendationContextResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $context = $this->resource;
        $recommendation = $context['recommendation'] ?? null;

        if ($recommendation !== null) {
            $context['recommendation'] = (new RfqAwardRecommendationResource($recommendation))->resolve($request);
        }

        return $context;
    }
}
