<?php

namespace Database\Seeders\Demo;

use Domains\Approval\Models\ApprovalTask;
use Domains\Award\Models\Award;
use Domains\Project\Models\ProcurementProject;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\Rfq;
use Domains\Vendor\Models\Vendor;

class DemoRoadmapPreviewSeeder
{
    private const RFQ_DUE_AT = '2026-05-29 12:00:00';
    private const APPROVAL_DUE_AT = '2026-05-22 12:00:00';
    private const DECIDED_AT = '2026-05-20 12:00:00';

    public function run(DemoSeedContext $context): void
    {
        $this->seedAcmePreview($context);
        $this->seedNorthwindPreview($context);
    }

    private function seedAcmePreview(DemoSeedContext $context): void
    {
        $tenant = $context->tenants->get('acme');
        $admin = $context->users->get('admin');
        $approver = $context->users->get('finance');

        $vendorRows = [
            'atlas' => ['Atlas Office Supplies', 'preferred', 'Office supplies', 'low'],
            'northstar' => ['Northstar Furniture Co', 'evaluation', 'Furniture', 'medium'],
            'secureworks' => ['SecureWorks Advisory', 'preferred', 'Professional services', 'low'],
            'papertrail' => ['Papertrail Logistics', 'restricted', 'Logistics', 'high'],
            'byteforge' => ['ByteForge Systems', 'evaluation', 'IT hardware', 'medium'],
            'greenline' => ['Greenline Facilities', 'preferred', 'Facilities', 'low'],
        ];

        foreach ($vendorRows as $key => [$name, $status, $category, $risk]) {
            $context->vendors->put(
                $key,
                Vendor::query()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'name' => $name],
                    [
                        'status' => $status,
                        'category' => $category,
                        'risk_rating' => $risk,
                        'metadata' => ['demo' => true],
                    ],
                ),
            );
        }

        $project = ProcurementProject::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'number' => 'PRJ-2026-0001'],
            [
                'owner_id' => $admin->id,
                'name' => 'HQ Workplace Refresh',
                'charter' => 'Coordinate related requisitions for the workspace refresh.',
                'status' => 'active',
                'budget_amount' => 120000,
                'currency' => 'USD',
                'department' => 'Operations',
                'cost_center' => 'OPS-100',
                'target_start_date' => '2026-06-01',
                'target_completion_date' => '2026-09-30',
                'metadata' => ['demo' => true],
            ],
        );
        $context->projects->put('workplace-refresh', $project);

        $rfq = Rfq::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'number' => 'RFQ-2026-0001'],
            [
                'project_id' => $project->id,
                'requisition_id' => $context->requisitions->get('office-refresh')->id,
                'title' => 'Office furniture package',
                'status' => 'open',
                'due_at' => self::RFQ_DUE_AT,
                'metadata' => ['invited_vendors' => 3],
            ],
        );
        $context->rfqs->put('office-furniture', $rfq);

        $quotation = Quotation::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'number' => 'QUO-2026-0001'],
            [
                'rfq_id' => $rfq->id,
                'vendor_id' => $context->vendors->get('northstar')->id,
                'status' => 'received',
                'total_amount' => 84500,
                'currency' => 'USD',
                'metadata' => ['lead_time_days' => 21],
            ],
        );
        $context->quotations->put('northstar-office', $quotation);

        $approvalTask = ApprovalTask::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'subject_type' => Quotation::class,
                'subject_id' => $quotation->id,
                'title' => 'Finance approval for office furniture package',
            ],
            [
                'approver_id' => $approver->id,
                'status' => 'pending',
                'due_at' => self::APPROVAL_DUE_AT,
                'metadata' => ['stage' => 'finance'],
            ],
        );
        $context->approvalTasks->put('office-finance', $approvalTask);

        $award = Award::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'number' => 'AWD-2026-0001'],
            [
                'project_id' => $project->id,
                'rfq_id' => $rfq->id,
                'quotation_id' => $quotation->id,
                'vendor_id' => $context->vendors->get('northstar')->id,
                'status' => 'recommended',
                'total_amount' => 84500,
                'currency' => 'USD',
                'decided_at' => self::DECIDED_AT,
                'metadata' => ['rationale' => 'Best delivery confidence'],
            ],
        );
        $context->awards->put('office-award', $award);
    }

    private function seedNorthwindPreview(DemoSeedContext $context): void
    {
        $tenant = $context->tenants->get('northwind');
        $owner = $context->users->get('vendor_manager');

        $vendorRows = [
            'harbor' => ['Harbor Industrial Supply', 'preferred', 'Warehouse supplies', 'low'],
            'metrofleet' => ['MetroFleet Services', 'evaluation', 'Fleet services', 'medium'],
            'civic-safety' => ['Civic Safety Partners', 'restricted', 'Safety equipment', 'high'],
        ];

        foreach ($vendorRows as $key => [$name, $status, $category, $risk]) {
            $context->vendors->put(
                $key,
                Vendor::query()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'name' => $name],
                    [
                        'status' => $status,
                        'category' => $category,
                        'risk_rating' => $risk,
                        'metadata' => ['demo' => true],
                    ],
                ),
            );
        }

        $project = ProcurementProject::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'number' => 'PRJ-2026-1001'],
            [
                'owner_id' => $owner->id,
                'name' => 'Northwind Warehouse Launch',
                'charter' => 'Coordinate related requisitions for the workspace refresh.',
                'status' => 'active',
                'budget_amount' => 78000,
                'currency' => 'USD',
                'department' => 'Operations',
                'cost_center' => 'OPS-100',
                'target_start_date' => '2026-06-01',
                'target_completion_date' => '2026-09-30',
                'metadata' => ['demo' => true],
            ],
        );
        $context->projects->put('warehouse-launch', $project);

        $rfq = Rfq::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'number' => 'RFQ-2026-1001'],
            [
                'project_id' => $project->id,
                'requisition_id' => $context->requisitions->get('warehouse-supplies')->id,
                'title' => 'Warehouse supply bundle',
                'status' => 'open',
                'due_at' => self::RFQ_DUE_AT,
                'metadata' => ['invited_vendors' => 2],
            ],
        );
        $context->rfqs->put('warehouse-supplies', $rfq);

        $quotation = Quotation::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'number' => 'QUO-2026-1001'],
            [
                'rfq_id' => $rfq->id,
                'vendor_id' => $context->vendors->get('harbor')->id,
                'status' => 'received',
                'total_amount' => 61200,
                'currency' => 'USD',
                'metadata' => ['lead_time_days' => 14],
            ],
        );
        $context->quotations->put('harbor-warehouse', $quotation);

        $approvalTask = ApprovalTask::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'subject_type' => Quotation::class,
                'subject_id' => $quotation->id,
                'title' => 'Buyer review for warehouse supply bundle',
            ],
            [
                'approver_id' => $owner->id,
                'status' => 'pending',
                'due_at' => self::APPROVAL_DUE_AT,
                'metadata' => ['stage' => 'buyer_review'],
            ],
        );
        $context->approvalTasks->put('warehouse-buyer-review', $approvalTask);

        $award = Award::query()->updateOrCreate(
            ['tenant_id' => $tenant->id, 'number' => 'AWD-2026-1001'],
            [
                'project_id' => $project->id,
                'rfq_id' => $rfq->id,
                'quotation_id' => $quotation->id,
                'vendor_id' => $context->vendors->get('harbor')->id,
                'status' => 'recommended',
                'total_amount' => 61200,
                'currency' => 'USD',
                'decided_at' => self::DECIDED_AT,
                'metadata' => ['rationale' => 'Best local availability'],
            ],
        );
        $context->awards->put('warehouse-award', $award);
    }
}
