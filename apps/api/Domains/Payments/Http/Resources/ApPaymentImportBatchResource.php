<?php

namespace Domains\Payments\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApPaymentImportBatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $rows = $this->resource['rows'] ?? [];
        $summary = $this->resource['summary'] ?? ['total' => 0, 'pending' => 0, 'reconciled' => 0, 'failed' => 0, 'discarded' => 0];

        return [
            'batchId' => $this->resource['batchId'],
            'rows' => ApPaymentImportResource::collection(collect($rows))->resolve(),
            'summary' => $summary,
        ];
    }
}
