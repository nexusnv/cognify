<?php

namespace Domains\Approval\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \Domains\Approval\Data\ApprovalPreviewData
 */
class ApprovalPreviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return $this->resource->toArray();
    }
}
