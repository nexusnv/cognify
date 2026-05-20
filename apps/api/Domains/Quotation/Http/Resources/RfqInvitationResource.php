<?php

namespace Domains\Quotation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RfqInvitationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => (string) $this->id,
            'tenantId' => (string) $this->tenant_id,
            'rfqId' => (string) $this->rfq_id,
            'status' => $this->status->value,
            'vendor' => [
                'id' => (string) $this->vendor->id,
                'name' => $this->vendor->name,
                'category' => $this->vendor->category,
                'status' => $this->vendor->status,
                'riskRating' => $this->vendor->risk_rating,
            ],
            'contactName' => $this->contact_name,
            'contactEmail' => $this->contact_email,
            'message' => $this->message,
            'responseDueAt' => $this->response_due_at?->toISOString(),
            'sentAt' => $this->sent_at?->toISOString(),
            'acknowledgedAt' => $this->acknowledged_at?->toISOString(),
            'declinedAt' => $this->declined_at?->toISOString(),
            'expiredAt' => $this->expired_at?->toISOString(),
            'cancelledAt' => $this->cancelled_at?->toISOString(),
            'cancelReason' => $this->cancel_reason,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
            'permissions' => [
                'canResend' => $user?->can('resend', $this->resource) ?? false,
                'canCancel' => $user?->can('cancel', $this->resource) ?? false,
                'canUpdateStatus' => $user?->can('updateStatus', $this->resource) ?? false,
            ],
        ];
    }
}
