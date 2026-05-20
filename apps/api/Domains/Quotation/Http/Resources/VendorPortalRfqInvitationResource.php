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
                'category' => $this->vendor->category,
                'riskRating' => $this->vendor->risk_rating,
            ],
            'rfq' => [
                'id' => (string) $this->rfq->id,
                'number' => $this->rfq->number,
                'title' => $this->rfq->title,
                'responseDueAt' => $this->rfq->response_due_at?->toISOString(),
                'scopeSummary' => $this->rfq->scope_summary,
                'responseInstructions' => $this->rfq->response_instructions,
                'requiredDocuments' => $this->rfq->required_documents ?? [],
                'lineItems' => $this->rfq->line_items ?? [],
            ],
        ];
    }
}
