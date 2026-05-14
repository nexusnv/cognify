<?php

namespace Database\Seeders\Demo;

use Domains\Requisition\Models\Requisition;
use Domains\Requisition\States\RequisitionStatus;

class DemoRequisitionSeeder
{
    private const SUBMITTED_AT = '2026-05-15 09:00:00';

    public function run(DemoSeedContext $context): void
    {
        $records = $this->records($context);

        foreach ($records as $key => [$tenantKey, $requesterKey, $number, $title, $status, $department, $costCenter, $items]) {
            $tenant = $context->tenants->get($tenantKey);
            $requester = $context->users->get($requesterKey);
            $requisition = Requisition::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'number' => $number],
                [
                    'requester_id' => $requester->id,
                    'title' => $title,
                    'business_justification' => "Demo justification for {$title}.",
                    'needed_by_date' => '2026-06-30',
                    'department' => $department,
                    'cost_center' => $costCenter,
                    'delivery_location' => 'Kuala Lumpur HQ',
                    'currency' => 'USD',
                    'status' => $status,
                    'submitted_at' => $status === RequisitionStatus::Draft ? null : self::SUBMITTED_AT,
                ],
            );

            $requisition->lineItems()->delete();

            foreach ($items as [$name, $quantity, $unitPrice]) {
                $requisition->lineItems()->create([
                    'name' => $name,
                    'quantity' => $quantity,
                    'unit_of_measure' => 'each',
                    'estimated_unit_price' => $unitPrice,
                    'currency' => 'USD',
                ]);
            }

            $context->requisitions->put($key, $requisition->refresh());
        }
    }

    /**
     * @return array<string, array{string, string, string, string, RequisitionStatus, string, string, list<array{string, int, int}>}>
     */
    private function records(DemoSeedContext $context): array
    {
        return [
            'office-refresh' => [
                'acme',
                'requester',
                'REQ-2026-0001',
                'HQ workplace refresh',
                RequisitionStatus::Submitted,
                'Operations',
                'CC-OPS-100',
                [
                    ['Ergonomic chair', 45, 420],
                    ['Adjustable desk', 45, 880],
                ],
            ],
            'laptops' => [
                'acme',
                'requester',
                'REQ-2026-0002',
                'Engineering laptop refresh',
                RequisitionStatus::Draft,
                'Engineering',
                'CC-ENG-220',
                [
                    ['Developer laptop', 18, 2450],
                    ['Docking station', 18, 240],
                ],
            ],
            'security-audit' => [
                'acme',
                'requester',
                'REQ-2026-0003',
                'Security audit services',
                RequisitionStatus::PendingApproval,
                'Security',
                'CC-SEC-310',
                [
                    ['SOC 2 readiness assessment', 1, 38000],
                ],
            ],
            'warehouse-supplies' => [
                'northwind',
                'vendor_manager',
                'REQ-2026-1001',
                'Regional warehouse supplies',
                RequisitionStatus::Submitted,
                'Operations',
                'CC-NW-410',
                [
                    ['Warehouse shelving', 20, 640],
                    ['Safety signage kit', 12, 90],
                ],
            ],
            'fleet-maintenance' => [
                'northwind',
                'vendor_manager',
                'REQ-2026-1002',
                'Fleet maintenance review',
                RequisitionStatus::PendingApproval,
                'Logistics',
                'CC-NW-520',
                [
                    ['Preventive maintenance package', 1, 24500],
                ],
            ],
        ];
    }
}
