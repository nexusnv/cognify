<?php

namespace Database\Seeders\Demo;

use Domains\Requisition\Models\Requisition;
use Domains\Requisition\States\RequisitionStatus;

class DemoRequisitionSeeder
{
    public function run(DemoSeedContext $context): void
    {
        $tenant = $context->tenants->get('acme');
        $requester = $context->users->get('requester');

        $records = [
            'office-refresh' => [
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
                'REQ-2026-0003',
                'Security audit services',
                RequisitionStatus::Submitted,
                'Security',
                'CC-SEC-310',
                [
                    ['SOC 2 readiness assessment', 1, 38000],
                ],
            ],
        ];

        foreach ($records as $key => [$number, $title, $status, $department, $costCenter, $items]) {
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
                    'submitted_at' => $status === RequisitionStatus::Submitted ? now() : null,
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
}
