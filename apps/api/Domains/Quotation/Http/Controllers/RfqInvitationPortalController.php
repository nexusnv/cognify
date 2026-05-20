<?php

namespace Domains\Quotation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\CurrentTenant;
use Domains\Quotation\Actions\RegenerateRfqInvitationPortalToken;
use Domains\Quotation\Actions\ResolveRfqInvitationPortalAccess;
use Domains\Quotation\Http\Requests\ResolveRfqInvitationPortalRequest;
use Domains\Quotation\Http\Resources\RfqInvitationPortalLinkResource;
use Domains\Quotation\Http\Resources\VendorPortalRfqInvitationResource;
use Illuminate\Http\Request;

class RfqInvitationPortalController extends Controller
{
    public function show(ResolveRfqInvitationPortalRequest $request, ResolveRfqInvitationPortalAccess $action): VendorPortalRfqInvitationResource
    {
        $invitation = $action->handle((string) $request->validated('token'), $request);

        return new VendorPortalRfqInvitationResource($invitation);
    }

    public function regenerate(
        Request $request,
        CurrentTenant $currentTenant,
        int $invitation,
        RegenerateRfqInvitationPortalToken $action
    ): RfqInvitationPortalLinkResource {
        $result = $action->handle($currentTenant->get(), $request->user(), $invitation);

        return new RfqInvitationPortalLinkResource($result['invitation'], $result['token']);
    }
}
