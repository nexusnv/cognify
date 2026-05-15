<?php

namespace Database\Seeders\Demo;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Approval\Models\ApprovalTask;
use Domains\Award\Models\Award;
use Domains\Project\Models\ProcurementProject;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\Rfq;
use Domains\Requisition\Models\Requisition;
use Domains\Vendor\Models\Vendor;
use Illuminate\Support\Collection;

class DemoSeedContext
{
    /** @var Collection<string, Tenant> */
    public Collection $tenants;

    /** @var Collection<string, User> */
    public Collection $users;

    /** @var Collection<string, Requisition> */
    public Collection $requisitions;

    /** @var Collection<string, Vendor> */
    public Collection $vendors;

    /** @var Collection<string, ProcurementProject> */
    public Collection $projects;

    /** @var Collection<string, Rfq> */
    public Collection $rfqs;

    /** @var Collection<string, Quotation> */
    public Collection $quotations;

    /** @var Collection<string, ApprovalTask> */
    public Collection $approvalTasks;

    /** @var Collection<string, Award> */
    public Collection $awards;

    public function __construct()
    {
        $this->tenants = collect();
        $this->users = collect();
        $this->requisitions = collect();
        $this->vendors = collect();
        $this->projects = collect();
        $this->rfqs = collect();
        $this->quotations = collect();
        $this->approvalTasks = collect();
        $this->awards = collect();
    }
}
