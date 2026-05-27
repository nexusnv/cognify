<?php

namespace Database\Seeders\Demo;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Approval\Models\ApprovalTask;
use Domains\Award\Models\Award;
use Domains\Project\Models\ProcurementProject;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\QuotationNormalization;
use Domains\Quotation\Models\QuotationScoringTemplate;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\Models\RfqAwardRecommendation;
use Domains\Quotation\Models\RfqScorecard;
use Domains\Quotation\Models\SourcingIntakeReview;
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

    /** @var Collection<string, QuotationNormalization> */
    public Collection $quotationNormalizations;

    /** @var Collection<string, QuotationScoringTemplate> */
    public Collection $quotationScoringTemplates;

    /** @var Collection<string, RfqScorecard> */
    public Collection $rfqScorecards;

    /** @var Collection<string, RfqAwardRecommendation> */
    public Collection $rfqAwardRecommendations;

    /** @var Collection<string, SourcingIntakeReview> */
    public Collection $sourcingIntakeReviews;

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
        $this->quotationNormalizations = collect();
        $this->quotationScoringTemplates = collect();
        $this->rfqScorecards = collect();
        $this->rfqAwardRecommendations = collect();
        $this->sourcingIntakeReviews = collect();
        $this->approvalTasks = collect();
        $this->awards = collect();
    }
}
