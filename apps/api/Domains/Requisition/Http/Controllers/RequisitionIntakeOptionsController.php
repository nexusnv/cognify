<?php

namespace Domains\Requisition\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Tenancy\CurrentTenant;
use Domains\Requisition\Models\RequisitionCostCenter;
use Domains\Requisition\Models\RequisitionDepartment;

class RequisitionIntakeOptionsController extends Controller
{
    public function __invoke(CurrentTenant $currentTenant)
    {
        $tenant = $currentTenant->get();

        return response()->json([
            'data' => [
                'departments' => RequisitionDepartment::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get(['name'])
                    ->map(fn (RequisitionDepartment $department): array => ['name' => $department->name])
                    ->values(),
                'costCenters' => RequisitionCostCenter::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('active', true)
                    ->orderBy('sort_order')
                    ->orderBy('code')
                    ->get(['code', 'name'])
                    ->map(fn (RequisitionCostCenter $costCenter): array => ['code' => $costCenter->code, 'name' => $costCenter->name])
                    ->values(),
                'currencies' => ['MYR', 'USD'],
                'units' => ['each', 'bundle', 'month', 'hour', 'day'],
            ],
        ]);
    }
}
