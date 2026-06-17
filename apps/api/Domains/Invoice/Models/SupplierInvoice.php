<?php

namespace Domains\Invoice\Models;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Attachment\Models\Attachment;
use Domains\Invoice\Models\Relations\UuidMorphMany;
use Domains\Invoice\Models\SupplierInvoiceMatchResult;
use Domains\Invoice\States\SupplierInvoiceStatus;
use Domains\PurchaseOrder\Models\PurchaseOrder;
use Domains\Vendor\Models\Vendor;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class SupplierInvoice extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'purchase_order_id',
        'vendor_id',
        'number',
        'invoice_number',
        'invoice_number_normalized',
        'status',
        'invoice_date',
        'due_date',
        'currency',
        'subtotal_amount',
        'tax_amount',
        'freight_amount',
        'total_amount',
        'notes',
        'captured_by_user_id',
        'captured_at',
        'review_started_by_user_id',
        'review_started_at',
        'reviewed_by_user_id',
        'reviewed_at',
        'review_notes',
        'review_checklist',
        'review_blockers',
        'lock_version',
        'matching_status',
        'exception_summary',
    ];

    protected function casts(): array
    {
        return [
            'status' => SupplierInvoiceStatus::class,
            'invoice_date' => 'immutable_date',
            'due_date' => 'immutable_date',
            'subtotal_amount' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'freight_amount' => 'decimal:4',
            'total_amount' => 'decimal:4',
            'captured_at' => 'datetime',
            'review_started_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'review_checklist' => 'array',
            'review_blockers' => 'array',
            'lock_version' => 'integer',
            'matching_status' => 'string',
            'exception_summary' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $invoice): void {
            if ($invoice->purchase_order_id !== null && $invoice->isDirty(['purchase_order_id', 'tenant_id'])) {
                $belongsToTenant = PurchaseOrder::query()
                    ->whereKey($invoice->purchase_order_id)
                    ->where('tenant_id', $invoice->tenant_id)
                    ->lockForUpdate()
                    ->exists();

                if (! $belongsToTenant) {
                    throw new InvalidArgumentException('Supplier invoice purchase order must belong to the same tenant.');
                }
            }

            if ($invoice->vendor_id !== null && $invoice->isDirty(['vendor_id', 'tenant_id'])) {
                $belongsToTenant = Vendor::query()
                    ->whereKey($invoice->vendor_id)
                    ->where('tenant_id', $invoice->tenant_id)
                    ->lockForUpdate()
                    ->exists();

                if (! $belongsToTenant) {
                    throw new InvalidArgumentException('Supplier invoice vendor must belong to the same tenant.');
                }
            }

            if ($invoice->captured_by_user_id !== null && $invoice->isDirty(['captured_by_user_id', 'tenant_id'])) {
                $belongsToTenant = User::query()
                    ->whereKey($invoice->captured_by_user_id)
                    ->whereHas('tenants', fn ($query) => $query->whereKey($invoice->tenant_id))
                    ->lockForUpdate()
                    ->exists();

                if (! $belongsToTenant) {
                    throw new InvalidArgumentException('Supplier invoice capturer must belong to the same tenant.');
                }
            }

            foreach (['review_started_by_user_id', 'reviewed_by_user_id'] as $userColumn) {
                if ($invoice->{$userColumn} !== null && $invoice->isDirty([$userColumn, 'tenant_id'])) {
                    $belongsToTenant = User::query()
                        ->whereKey($invoice->{$userColumn})
                        ->whereHas('tenants', fn ($query) => $query->whereKey($invoice->tenant_id))
                        ->lockForUpdate()
                        ->exists();

                    if (! $belongsToTenant) {
                        throw new InvalidArgumentException('Supplier invoice reviewer must belong to the same tenant.');
                    }
                }
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function capturedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captured_by_user_id');
    }

    public function reviewStartedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'review_started_by_user_id');
    }

    public function reviewedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SupplierInvoiceLine::class)->orderBy('line_number');
    }

    public function matchResults(): HasMany
    {
        return $this->hasMany(SupplierInvoiceMatchResult::class)->orderBy('created_at');
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(SupplierInvoiceException::class)->orderBy('created_at');
    }

    public function attachments(): MorphMany
    {
        $relation = $this->morphMany(Attachment::class, 'attachable');

        return $relation->getQuery()->getConnection()->getDriverName() === 'pgsql'
            ? new UuidMorphMany($relation->getQuery(), $this, 'attachable_type', 'attachable_id', 'id')
            : $relation;
    }

    public function statusState(): SupplierInvoiceStatus
    {
        return $this->status instanceof SupplierInvoiceStatus
            ? $this->status
            : SupplierInvoiceStatus::from((string) $this->getAttribute('status'));
    }

    public function assertLockVersion(int $lockVersion): void
    {
        if ((int) $this->lock_version !== $lockVersion) {
            throw new ConflictHttpException('Supplier invoice was updated by another user. Refresh and try again.');
        }
    }
}
