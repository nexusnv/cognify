<?php

namespace Domains\PurchaseOrder\Models;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Approval\Models\ApprovalInstance;
use Domains\Project\Models\ProcurementProject;
use Domains\PurchaseOrder\States\PurchaseOrderRequestHandoffStatus;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\QuotationVersion;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\Models\RfqAwardRecommendation;
use Domains\Requisition\Models\Requisition;
use Domains\Vendor\Models\Vendor;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class PurchaseOrderRequestHandoff extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
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
        'delivery_attention',
        'finance_note',
        'export_memo',
        'requested_by_user_id',
        'ready_by_user_id',
        'ready_at',
        'cancelled_by_user_id',
        'cancelled_at',
        'cancelled_reason',
        'last_exported_by_user_id',
        'last_exported_at',
        'last_export_format',
        'source_snapshot',
        'line_snapshot',
        'approval_snapshot',
        'evidence_snapshot',
        'readiness_warnings',
        'lock_version',
    ];

    protected function casts(): array
    {
        return [
            'status' => PurchaseOrderRequestHandoffStatus::class,
            'subtotal_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'freight_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'requested_po_date' => 'immutable_date',
            'ready_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'last_exported_at' => 'datetime',
            'source_snapshot' => 'array',
            'line_snapshot' => 'array',
            'approval_snapshot' => 'array',
            'evidence_snapshot' => 'array',
            'readiness_warnings' => 'array',
            'lock_version' => 'integer',
        ];
    }

    public function statusState(): PurchaseOrderRequestHandoffStatus
    {
        return $this->status instanceof PurchaseOrderRequestHandoffStatus
            ? $this->status
            : PurchaseOrderRequestHandoffStatus::from((string) $this->getAttribute('status'));
    }

    public function assertLockVersion(int $lockVersion): void
    {
        if ((int) $this->lock_version !== $lockVersion) {
            throw new ConflictHttpException('The PO handoff has changed. Reload and try again.');
        }
    }

    protected static function booted(): void
    {
        static::saving(function (self $handoff): void {
            DB::transaction(function () use ($handoff): void {
                $handoff->assertBelongsToTenant(
                    relationKey: 'rfq_award_recommendation_id',
                    dirtyMessage: ['rfq_award_recommendation_id', 'tenant_id'],
                    query: RfqAwardRecommendation::query(),
                    error: 'PO handoff award recommendation must belong to the same tenant.',
                );
                $handoff->assertBelongsToTenant(
                    relationKey: 'approval_instance_id',
                    dirtyMessage: ['approval_instance_id', 'tenant_id'],
                    query: ApprovalInstance::query(),
                    error: 'PO handoff approval instance must belong to the same tenant.',
                );
                $handoff->assertBelongsToTenant(
                    relationKey: 'rfq_id',
                    dirtyMessage: ['rfq_id', 'tenant_id'],
                    query: Rfq::query(),
                    error: 'PO handoff RFQ must belong to the same tenant.',
                );
                $handoff->assertBelongsToTenant(
                    relationKey: 'requisition_id',
                    dirtyMessage: ['requisition_id', 'tenant_id'],
                    query: Requisition::query(),
                    error: 'PO handoff requisition must belong to the same tenant.',
                );
                $handoff->assertBelongsToTenant(
                    relationKey: 'project_id',
                    dirtyMessage: ['project_id', 'tenant_id'],
                    query: ProcurementProject::query(),
                    error: 'PO handoff project must belong to the same tenant.',
                );
                $handoff->assertBelongsToTenant(
                    relationKey: 'vendor_id',
                    dirtyMessage: ['vendor_id', 'tenant_id'],
                    query: Vendor::query(),
                    error: 'PO handoff vendor must belong to the same tenant.',
                );
                $handoff->assertBelongsToTenant(
                    relationKey: 'quotation_id',
                    dirtyMessage: ['quotation_id', 'tenant_id'],
                    query: Quotation::query(),
                    error: 'PO handoff quotation must belong to the same tenant.',
                );
                $handoff->assertBelongsToTenant(
                    relationKey: 'quotation_version_id',
                    dirtyMessage: ['quotation_version_id', 'tenant_id'],
                    query: QuotationVersion::query(),
                    error: 'PO handoff quotation version must belong to the same tenant.',
                );
                $handoff->assertUserBelongsToTenant(
                    userKey: 'requested_by_user_id',
                    dirtyMessage: ['requested_by_user_id', 'tenant_id'],
                    error: 'PO handoff requester must belong to the same tenant.',
                );
                $handoff->assertUserBelongsToTenant(
                    userKey: 'ready_by_user_id',
                    dirtyMessage: ['ready_by_user_id', 'tenant_id'],
                    error: 'PO handoff ready actor must belong to the same tenant.',
                );
                $handoff->assertUserBelongsToTenant(
                    userKey: 'cancelled_by_user_id',
                    dirtyMessage: ['cancelled_by_user_id', 'tenant_id'],
                    error: 'PO handoff cancellation actor must belong to the same tenant.',
                );
                $handoff->assertUserBelongsToTenant(
                    userKey: 'last_exported_by_user_id',
                    dirtyMessage: ['last_exported_by_user_id', 'tenant_id'],
                    error: 'PO handoff export actor must belong to the same tenant.',
                );
            });
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
    public function requestedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function readyByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ready_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cancelledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function lastExportedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_exported_by_user_id');
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
