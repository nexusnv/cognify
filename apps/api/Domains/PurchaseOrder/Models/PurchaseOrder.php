<?php

namespace Domains\PurchaseOrder\Models;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Approval\Models\ApprovalInstance;
use Domains\Fulfillment\Models\Shipment;
use Domains\Project\Models\ProcurementProject;
use Domains\PurchaseOrder\Models\PurchaseOrderChangeOrder;
use Domains\PurchaseOrder\States\PurchaseOrderStatus;
use Domains\Receiving\Models\GoodsReceipt;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\QuotationVersion;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\Models\RfqAwardRecommendation;
use Domains\Requisition\Models\Requisition;
use Domains\Vendor\Models\Vendor;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class PurchaseOrder extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'purchase_order_request_handoff_id',
        'rfq_award_recommendation_id',
        'approval_instance_id',
        'rfq_id',
        'requisition_id',
        'project_id',
        'vendor_id',
        'quotation_id',
        'quotation_version_id',
        'number',
        'status',
        'currency',
        'subtotal_amount',
        'tax_amount',
        'freight_amount',
        'discount_amount',
        'total_amount',
        'requested_po_date',
        'expected_delivery_date',
        'billing_name',
        'billing_address',
        'shipping_name',
        'shipping_address',
        'delivery_attention',
        'payment_terms',
        'delivery_terms',
        'buyer_note',
        'finance_note',
        'source_snapshot',
        'approval_snapshot',
        'evidence_snapshot',
        'created_by_user_id',
        'ready_for_review_by_user_id',
        'ready_for_review_at',
        'approval_submitted_by_user_id',
        'approval_submitted_at',
        'approved_by_user_id',
        'approved_at',
        'rejected_by_user_id',
        'rejected_at',
        'rejected_reason',
        'changes_requested_by_user_id',
        'changes_requested_at',
        'changes_requested_reason',
        'changes_requested_fields',
        'issued_by_user_id',
        'issued_at',
        'issue_method',
        'supplier_contact_name',
        'supplier_contact_email',
        'issue_message',
        'supplier_version',
        'supplier_version_number',
        'last_supplier_exported_by_user_id',
        'last_supplier_exported_at',
        'last_supplier_export_format',
        'acknowledged_by_user_id',
        'acknowledged_at',
        'acknowledged_contact_name',
        'acknowledgement_reference',
        'acknowledgement_note',
        'current_change_order_id',
        'current_supplier_version_number',
        'change_order_count',
        'cancelled_by_user_id',
        'cancelled_at',
        'cancelled_reason',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'status' => PurchaseOrderStatus::class,
            'subtotal_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'freight_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'requested_po_date' => 'immutable_date',
            'expected_delivery_date' => 'immutable_date',
            'billing_address' => 'array',
            'shipping_address' => 'array',
            'source_snapshot' => 'array',
            'approval_snapshot' => 'array',
            'evidence_snapshot' => 'array',
            'ready_for_review_at' => 'datetime',
            'approval_submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'changes_requested_at' => 'datetime',
            'changes_requested_fields' => 'array',
            'issued_at' => 'datetime',
            'supplier_version' => 'array',
            'supplier_version_number' => 'integer',
            'current_supplier_version_number' => 'integer',
            'change_order_count' => 'integer',
            'last_supplier_exported_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'lock_version' => 'integer',
        ];
    }

    public function statusState(): PurchaseOrderStatus
    {
        return $this->status instanceof PurchaseOrderStatus
            ? $this->status
            : PurchaseOrderStatus::from((string) $this->getAttribute('status'));
    }

    public function assertLockVersion(int $lockVersion): void
    {
        if ((int) $this->lock_version !== $lockVersion) {
            throw new ConflictHttpException('The purchase order has changed. Reload and try again.');
        }
    }

    protected static function booted(): void
    {
        static::saving(function (self $purchaseOrder): void {
            $purchaseOrder->assertBelongsToTenant(
                relationKey: 'purchase_order_request_handoff_id',
                dirtyMessage: ['purchase_order_request_handoff_id', 'tenant_id'],
                query: PurchaseOrderRequestHandoff::query(),
                error: 'Purchase order handoff must belong to the same tenant.',
            );
            $purchaseOrder->assertBelongsToTenant(
                relationKey: 'rfq_award_recommendation_id',
                dirtyMessage: ['rfq_award_recommendation_id', 'tenant_id'],
                query: RfqAwardRecommendation::query(),
                error: 'Purchase order award recommendation must belong to the same tenant.',
            );
            $purchaseOrder->assertBelongsToTenant(
                relationKey: 'approval_instance_id',
                dirtyMessage: ['approval_instance_id', 'tenant_id'],
                query: ApprovalInstance::query(),
                error: 'Purchase order approval instance must belong to the same tenant.',
            );
            $purchaseOrder->assertBelongsToTenant(
                relationKey: 'rfq_id',
                dirtyMessage: ['rfq_id', 'tenant_id'],
                query: Rfq::query(),
                error: 'Purchase order RFQ must belong to the same tenant.',
            );
            $purchaseOrder->assertBelongsToTenant(
                relationKey: 'requisition_id',
                dirtyMessage: ['requisition_id', 'tenant_id'],
                query: Requisition::query(),
                error: 'Purchase order requisition must belong to the same tenant.',
            );
            $purchaseOrder->assertBelongsToTenant(
                relationKey: 'project_id',
                dirtyMessage: ['project_id', 'tenant_id'],
                query: ProcurementProject::query(),
                error: 'Purchase order project must belong to the same tenant.',
            );
            $purchaseOrder->assertBelongsToTenant(
                relationKey: 'vendor_id',
                dirtyMessage: ['vendor_id', 'tenant_id'],
                query: Vendor::query(),
                error: 'Purchase order vendor must belong to the same tenant.',
            );
            $purchaseOrder->assertBelongsToTenant(
                relationKey: 'quotation_id',
                dirtyMessage: ['quotation_id', 'tenant_id'],
                query: Quotation::query(),
                error: 'Purchase order quotation must belong to the same tenant.',
            );
            $purchaseOrder->assertBelongsToTenant(
                relationKey: 'quotation_version_id',
                dirtyMessage: ['quotation_version_id', 'tenant_id'],
                query: QuotationVersion::query(),
                error: 'Purchase order quotation version must belong to the same tenant.',
            );
            $purchaseOrder->assertUserBelongsToTenant(
                userKey: 'created_by_user_id',
                dirtyMessage: ['created_by_user_id', 'tenant_id'],
                error: 'Purchase order creator must belong to the same tenant.',
            );
            $purchaseOrder->assertUserBelongsToTenant(
                userKey: 'ready_for_review_by_user_id',
                dirtyMessage: ['ready_for_review_by_user_id', 'tenant_id'],
                error: 'Purchase order ready-for-review actor must belong to the same tenant.',
            );
            $purchaseOrder->assertUserBelongsToTenant(
                userKey: 'approval_submitted_by_user_id',
                dirtyMessage: ['approval_submitted_by_user_id', 'tenant_id'],
                error: 'Purchase order approval submitter must belong to the same tenant.',
            );
            $purchaseOrder->assertUserBelongsToTenant(
                userKey: 'approved_by_user_id',
                dirtyMessage: ['approved_by_user_id', 'tenant_id'],
                error: 'Purchase order approver must belong to the same tenant.',
            );
            $purchaseOrder->assertUserBelongsToTenant(
                userKey: 'rejected_by_user_id',
                dirtyMessage: ['rejected_by_user_id', 'tenant_id'],
                error: 'Purchase order rejector must belong to the same tenant.',
            );
            $purchaseOrder->assertUserBelongsToTenant(
                userKey: 'changes_requested_by_user_id',
                dirtyMessage: ['changes_requested_by_user_id', 'tenant_id'],
                error: 'Purchase order changes requester must belong to the same tenant.',
            );
            $purchaseOrder->assertUserBelongsToTenant(
                userKey: 'issued_by_user_id',
                dirtyMessage: ['issued_by_user_id', 'tenant_id'],
                error: 'Purchase order issuer must belong to the same tenant.',
            );
            $purchaseOrder->assertUserBelongsToTenant(
                userKey: 'last_supplier_exported_by_user_id',
                dirtyMessage: ['last_supplier_exported_by_user_id', 'tenant_id'],
                error: 'Purchase order supplier export actor must belong to the same tenant.',
            );
            $purchaseOrder->assertUserBelongsToTenant(
                userKey: 'acknowledged_by_user_id',
                dirtyMessage: ['acknowledged_by_user_id', 'tenant_id'],
                error: 'Purchase order acknowledgement actor must belong to the same tenant.',
            );
            $purchaseOrder->assertUserBelongsToTenant(
                userKey: 'cancelled_by_user_id',
                dirtyMessage: ['cancelled_by_user_id', 'tenant_id'],
                error: 'Purchase order cancellation actor must belong to the same tenant.',
            );
            $purchaseOrder->assertBelongsToTenant(
                relationKey: 'current_change_order_id',
                dirtyMessage: ['current_change_order_id', 'tenant_id'],
                query: PurchaseOrderChangeOrder::query(),
                error: 'Purchase order current change order must belong to the same tenant.',
            );
        });
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<PurchaseOrderRequestHandoff, $this>
     */
    public function handoff(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderRequestHandoff::class, 'purchase_order_request_handoff_id');
    }

    /**
     * @return BelongsTo<RfqAwardRecommendation, $this>
     */
    public function recommendation(): BelongsTo
    {
        return $this->belongsTo(RfqAwardRecommendation::class, 'rfq_award_recommendation_id');
    }

    /**
     * @return BelongsTo<ApprovalInstance, $this>
     */
    public function approvalInstance(): BelongsTo
    {
        return $this->belongsTo(ApprovalInstance::class, 'approval_instance_id');
    }

    /**
     * @return BelongsTo<Rfq, $this>
     */
    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class);
    }

    /**
     * @return BelongsTo<Requisition, $this>
     */
    public function requisition(): BelongsTo
    {
        return $this->belongsTo(Requisition::class);
    }

    /**
     * @return BelongsTo<ProcurementProject, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(ProcurementProject::class, 'project_id');
    }

    /**
     * @return BelongsTo<Vendor, $this>
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * @return BelongsTo<Quotation, $this>
     */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /**
     * @return BelongsTo<QuotationVersion, $this>
     */
    public function quotationVersion(): BelongsTo
    {
        return $this->belongsTo(QuotationVersion::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function readyForReviewByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ready_for_review_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvalSubmittedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approval_submitted_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function rejectedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function changesRequestedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changes_requested_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function lastSupplierExportedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_supplier_exported_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function acknowledgedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    /**
     * @return HasMany<PurchaseOrderLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class)->orderBy('line_number');
    }

    /**
     * @return HasMany<Shipment, $this>
     */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class)->orderByDesc('shipment_date')->orderByDesc('id');
    }

    /**
     * @return HasMany<PurchaseOrderChangeOrder, $this>
     */
    public function changeOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrderChangeOrder::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    /**
     * @return HasMany<GoodsReceipt, $this>
     */
    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    /**
     * @return BelongsTo<PurchaseOrderChangeOrder, $this>
     */
    public function currentChangeOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderChangeOrder::class, 'current_change_order_id');
    }

    /**
     * @param  array<int, string>  $dirtyMessage
     */
    private function assertBelongsToTenant(string $relationKey, array $dirtyMessage, mixed $query, string $error): void
    {
        if ($this->{$relationKey} === null || ! $this->isDirty($dirtyMessage)) {
            return;
        }

        $belongsToTenant = $query
            ->whereKey($this->{$relationKey})
            ->where('tenant_id', $this->tenant_id)
            ->lockForUpdate()
            ->exists();

        if (! $belongsToTenant) {
            throw new InvalidArgumentException($error);
        }
    }

    /**
     * @param  array<int, string>  $dirtyMessage
     */
    private function assertUserBelongsToTenant(string $userKey, array $dirtyMessage, string $error): void
    {
        if ($this->{$userKey} === null || ! $this->isDirty($dirtyMessage)) {
            return;
        }

        $belongsToTenant = User::query()
            ->whereKey($this->{$userKey})
            ->whereHas('tenants', fn ($query) => $query->whereKey($this->tenant_id))
            ->lockForUpdate()
            ->exists();

        if (! $belongsToTenant) {
            throw new InvalidArgumentException($error);
        }
    }
}
