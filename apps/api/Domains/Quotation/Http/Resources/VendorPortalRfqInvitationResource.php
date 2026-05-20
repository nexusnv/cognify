<?php

namespace Domains\Quotation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VendorPortalRfqInvitationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'invitation' => [
                'id' => (string) $this->id,
                'status' => $this->status->value,
                'message' => $this->message,
                'responseDueAt' => $this->response_due_at?->toISOString(),
                'portalExpiresAt' => $this->portal_token_expires_at?->toISOString(),
            ],
            'tenant' => [
                'id' => (string) $this->tenant->id,
                'name' => $this->tenant->name,
            ],
            'vendor' => [
                'id' => (string) $this->vendor->id,
                'name' => $this->vendor->name,
                'contactName' => $this->contact_name,
                'contactEmail' => $this->contact_email,
            ],
            'rfq' => [
                'id' => (string) $this->rfq->id,
                'number' => $this->rfq->number,
                'title' => $this->rfq->title,
                'responseDueAt' => $this->rfq->response_due_at?->toISOString(),
                'scopeSummary' => $this->rfq->scope_summary,
                'responseInstructions' => $this->rfq->response_instructions,
                'requiredDocuments' => $this->requiredDocuments(),
                'lineItems' => $this->lineItems(),
            ],
        ];
    }

    /**
     * @return array<int, array{key: mixed, label: mixed, required: bool}>
     */
    private function requiredDocuments(): array
    {
        return collect($this->rfq->required_documents ?? [])
            ->map(fn ($document): array => [
                'key' => data_get($document, 'key'),
                'label' => data_get($document, 'label'),
                'required' => (bool) data_get($document, 'required', false),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function lineItems(): array
    {
        return collect($this->rfq->line_items ?? [])
            ->map(function ($lineItem): array {
                $description = data_get($lineItem, 'description');
                $name = data_get($lineItem, 'name');
                $unit = data_get($lineItem, 'unit');
                $unitOfMeasure = data_get($lineItem, 'unit_of_measure');

                return [
                    'name' => $name,
                    'description' => $description ?? $name,
                    'quantity' => data_get($lineItem, 'quantity'),
                    'unit' => $unit ?? $unitOfMeasure,
                    'notes' => data_get($lineItem, 'notes'),
                    'unitOfMeasure' => $unitOfMeasure,
                    'estimatedUnitPrice' => data_get($lineItem, 'estimated_unit_price'),
                    'currency' => data_get($lineItem, 'currency'),
                ];
            })
            ->values()
            ->all();
    }
}
