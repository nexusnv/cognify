<?php

namespace Domains\Approval\Http\Resources;

use Domains\Approval\Data\ApprovalPreviewData;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ApprovalPreviewData
 */
class ApprovalPreviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return $this->resource->toArray();
    }
}
