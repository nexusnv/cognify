<?php

namespace Domains\Approval\Actions;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Approval\Models\ApprovalInstance;
use Domains\Requisition\Models\Requisition;

class RouteRequisitionForApproval
{
    public function __construct(private readonly RouteSubjectForApproval $routeSubjectForApproval) {}

    public function handle(Tenant $tenant, User $actor, Requisition $requisition): ApprovalInstance
    {
        return $this->routeSubjectForApproval->handle($tenant, $actor, $requisition);
    }
}
