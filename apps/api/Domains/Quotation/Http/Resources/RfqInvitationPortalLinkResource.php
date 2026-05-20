<?php

namespace Domains\Quotation\Http\Resources;

use Domains\Quotation\Models\RfqInvitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RfqInvitationPortalLinkResource extends JsonResource
{
    public function __construct(
        private readonly RfqInvitation $invitation,
        private readonly string $token
    ) {
        parent::__construct($invitation);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'invitationId' => (string) $this->invitation->id,
            'token' => $this->token,
            'portalUrl' => "/vendor/rfq-invitations/{$this->token}",
            'expiresAt' => $this->invitation->portal_token_expires_at?->toISOString(),
        ];
    }
}
