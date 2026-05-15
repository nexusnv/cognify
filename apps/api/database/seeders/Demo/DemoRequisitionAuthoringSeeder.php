<?php

namespace Database\Seeders\Demo;

use App\Tenancy\Tenant;
use Domains\Requisition\Models\RequisitionCostCenter;
use Domains\Requisition\Models\RequisitionDepartment;
use Domains\Requisition\Models\RequisitionItemSuggestion;
use Domains\Requisition\Models\RequisitionTemplate;

class DemoRequisitionAuthoringSeeder
{
    public function run(): void
    {
        Tenant::query()->each(function (Tenant $tenant): void {
            foreach ([['Procurement', 1], ['Operations', 2], ['IT', 3], ['Finance', 4]] as [$name, $sort]) {
                RequisitionDepartment::query()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'name' => $name],
                    ['active' => true, 'sort_order' => $sort],
                );
            }

            foreach ([['OPS-110', 'Operations', 1], ['IT-210', 'Information Technology', 2], ['FIN-310', 'Finance', 3]] as [$code, $name, $sort]) {
                RequisitionCostCenter::query()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'code' => $code],
                    ['name' => $name, 'active' => true, 'sort_order' => $sort],
                );
            }

            RequisitionTemplate::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'name' => 'IT equipment'],
                [
                    'description' => 'Laptop, monitor, and accessory purchases.',
                    'category' => 'it_equipment',
                    'defaults' => [
                        'department' => 'IT',
                        'costCenter' => 'IT-210',
                        'businessJustification' => 'Provision or replace equipment required for business operations.',
                        'lineItems' => [[
                            'name' => 'Laptop',
                            'quantity' => 1,
                            'unit' => 'each',
                            'estimatedUnitPrice' => 1800,
                            'currency' => 'MYR',
                        ]],
                    ],
                    'active' => true,
                    'sort_order' => 1,
                ],
            );

            RequisitionTemplate::query()->updateOrCreate(
                ['tenant_id' => $tenant->id, 'name' => 'SaaS subscription'],
                [
                    'description' => 'Software subscription or renewal request.',
                    'category' => 'saas_subscription',
                    'defaults' => [
                        'department' => 'IT',
                        'costCenter' => 'IT-210',
                        'businessJustification' => 'Maintain software access required for business continuity.',
                        'lineItems' => [[
                            'name' => 'SaaS subscription',
                            'quantity' => 12,
                            'unit' => 'month',
                            'estimatedUnitPrice' => 250,
                            'currency' => 'MYR',
                        ]],
                    ],
                    'active' => true,
                    'sort_order' => 2,
                ],
            );

            foreach ([
                ['Laptop', 'it_equipment', 'each', 1800, ['notebook', 'computer'], 1],
                ['Monitor', 'it_equipment', 'each', 700, ['display', 'screen'], 2],
                ['SaaS subscription', 'saas_subscription', 'month', 250, ['software', 'license'], 3],
                ['Packing box bundle', 'office_supplies', 'bundle', 170, ['boxes', 'packaging'], 4],
            ] as [$name, $category, $unit, $price, $aliases, $sort]) {
                RequisitionItemSuggestion::query()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'name' => $name],
                    [
                        'category' => $category,
                        'unit' => $unit,
                        'estimated_unit_price' => $price,
                        'currency' => 'MYR',
                        'aliases' => $aliases,
                        'active' => true,
                        'sort_order' => $sort,
                    ],
                );
            }
        });
    }
}
