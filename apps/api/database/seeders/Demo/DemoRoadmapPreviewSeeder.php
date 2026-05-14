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
    public function run(DemoSeedContext $context): void
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
                'status' => 'active',
                'budget_amount' => 120000,
                'currency' => 'USD',
                'metadata' => ['department' => 'Operations'],
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
                'due_at' => now()->addDays(14),
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
                'due_at' => now()->addDays(7),
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
                'decided_at' => now(),
                'metadata' => ['rationale' => 'Best delivery confidence'],
            ],
        );
        $context->awards->put('office-award', $award);
    }
}
