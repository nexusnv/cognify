<?php

namespace Domains\Quotation\Actions;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\Models\RfqInvitation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class RegenerateRfqInvitationPortalToken
{
    public function __construct(private readonly EnsureRfqInvitationPortalToken $tokens) {}

    /**
     * @return array{invitation: RfqInvitation, token: string}
     */
    public function handle(Tenant $tenant, User $actor, int $invitationId): array
    {
        return DB::transaction(function () use ($tenant, $actor, $invitationId): array {
            $invitation = RfqInvitation::query()
                ->with('vendor', 'rfq')
                ->where('tenant_id', $tenant->id)
                ->whereKey($invitationId)
                ->lockForUpdate()
                ->first();

            if ($invitation === null) {
                throw new NotFoundHttpException();
            }

            Gate::forUser($actor)->authorize('regeneratePortalLink', $invitation);

            $result = $this->tokens->handle($tenant, $actor, $invitation, true);

            return [
                'invitation' => $result['invitation'],
                'token' => (string) $result['token'],
            ];
        });
    }
}
