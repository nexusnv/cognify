<?php

namespace Domains\PurchaseOrder\Actions;

use App\Models\User;
use Domains\Approval\Actions\RouteSubjectForApproval;
use Domains\PurchaseOrder\Models\PurchaseOrder;
use Domains\PurchaseOrder\States\PurchaseOrderStatus;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class SubmitPurchaseOrderForApproval
{
    public function __construct(private readonly RouteSubjectForApproval $routeSubjectForApproval) {}

    public function handle(PurchaseOrder $purchaseOrder, User $actor, int $lockVersion): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $actor, $lockVersion): PurchaseOrder {
            $purchaseOrder = PurchaseOrder::query()
                ->whereKey($purchaseOrder->id)
                ->where('tenant_id', $purchaseOrder->tenant_id)
                ->lockForUpdate()
                ->with('lines')
                ->firstOrFail();

            if (! in_array($purchaseOrder->statusState(), [PurchaseOrderStatus::ReadyForReview, PurchaseOrderStatus::ChangesRequested], true)) {
                throw new ConflictHttpException('Only ready or changes-requested purchase orders can be submitted for approval.');
            }

            $purchaseOrder->assertLockVersion($lockVersion);
            $this->assertApprovalReadiness($purchaseOrder);

            $this->routeSubjectForApproval->handle($purchaseOrder->tenant, $actor, $purchaseOrder);

            return $purchaseOrder->fresh('lines');
        });
    }

    private function assertApprovalReadiness(PurchaseOrder $purchaseOrder): void
    {
        if ($purchaseOrder->lines->isEmpty()) {
            throw new ConflictHttpException('Purchase order requires at least one line before approval.');
        }

        $required = [
            'billing_name' => $purchaseOrder->billing_name,
            'billing_address' => $purchaseOrder->billing_address,
            'shipping_name' => $purchaseOrder->shipping_name,
            'shipping_address' => $purchaseOrder->shipping_address,
            'payment_terms' => $purchaseOrder->payment_terms,
            'vendor_id' => $purchaseOrder->vendor_id,
            'currency' => $purchaseOrder->currency,
            'total_amount' => $purchaseOrder->total_amount,
        ];

        foreach ($required as $value) {
            if ($value === null || (is_string($value) && trim($value) === '') || (is_array($value) && $value === [])) {
                throw new ConflictHttpException('Purchase order requires vendor, lines, billing, shipping, totals, and payment terms before approval.');
            }
        }
    }
}
