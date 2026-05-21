<?php

namespace Domains\Quotation\Models;

use App\Tenancy\Tenant;
use App\Models\User;
use Domains\Attachment\Models\Attachment;
use Domains\Quotation\States\QuotationStatus;
use Domains\Quotation\States\QuotationSubmissionSource;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\Models\QuotationLineItem;
use Domains\Quotation\Models\RfqInvitation;
use Domains\Vendor\Models\Vendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class Quotation extends Model
{
    protected $fillable = [
        'tenant_id',
        'rfq_id',
        'vendor_id',
        'rfq_invitation_id',
        'current_version_id',
        'version_count',
        'number',
        'status',
        'submission_source',
        'submitted_at',
        'submitted_by_user_id',
        'submitted_by_vendor_contact',
        'file_count',
        'latest_received_at',
        'quotation_reference',
        'quoted_at',
        'valid_until',
        'subtotal_amount',
        'tax_amount',
        'freight_amount',
        'discount_amount',
        'payment_terms',
        'delivery_terms',
        'lead_time_days',
        'warranty_terms',
        'exclusions',
        'compliance_notes',
        'buyer_notes',
        'vendor_notes',
        'manual_entry_complete',
        'manual_entry_missing_fields',
        'manual_entry_saved_at',
        'manual_entry_saved_source',
        'total_amount',
        'currency',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => QuotationStatus::class,
            'submission_source' => QuotationSubmissionSource::class,
            'submitted_at' => 'immutable_datetime',
            'submitted_by_vendor_contact' => 'array',
            'version_count' => 'integer',
            'file_count' => 'integer',
            'latest_received_at' => 'immutable_datetime',
            'quoted_at' => 'immutable_date',
            'valid_until' => 'immutable_date',
            'subtotal_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'freight_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'lead_time_days' => 'integer',
            'manual_entry_complete' => 'boolean',
            'manual_entry_missing_fields' => 'array',
            'manual_entry_saved_at' => 'immutable_datetime',
            'total_amount' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $quotation): void {
            DB::transaction(function () use ($quotation): void {
                if ($quotation->rfq_id !== null && ($quotation->isDirty('rfq_id') || $quotation->isDirty('tenant_id') || $quotation->isDirty('rfq_invitation_id'))) {
                    $belongsToTenant = Rfq::query()
                        ->whereKey($quotation->rfq_id)
                        ->where('tenant_id', $quotation->tenant_id)
                        ->lockForUpdate()
                        ->exists();

                    if (! $belongsToTenant) {
                        throw new InvalidArgumentException('Quotation RFQ must belong to the same tenant.');
                    }
                }

                if ($quotation->vendor_id !== null && ($quotation->isDirty('vendor_id') || $quotation->isDirty('tenant_id'))) {
                    $belongsToTenant = Vendor::query()
                        ->whereKey($quotation->vendor_id)
                        ->where('tenant_id', $quotation->tenant_id)
                        ->lockForUpdate()
                        ->exists();

                    if (! $belongsToTenant) {
                        throw new InvalidArgumentException('Quotation vendor must belong to the same tenant.');
                    }
                }

                if ($quotation->rfq_invitation_id !== null && ($quotation->isDirty('rfq_invitation_id') || $quotation->isDirty('tenant_id') || $quotation->isDirty('rfq_id') || $quotation->isDirty('vendor_id'))) {
                    $belongsToTenant = RfqInvitation::query()
                        ->whereKey($quotation->rfq_invitation_id)
                        ->where('tenant_id', $quotation->tenant_id)
                        ->where('rfq_id', $quotation->rfq_id)
                        ->where('vendor_id', $quotation->vendor_id)
                        ->lockForUpdate()
                        ->exists();

                    if (! $belongsToTenant) {
                        throw new InvalidArgumentException('Quotation invitation must belong to the same tenant, RFQ, and vendor.');
                    }
                }

                if ($quotation->current_version_id !== null && ($quotation->isDirty('current_version_id') || $quotation->isDirty('tenant_id'))) {
                    $belongsToQuotation = QuotationVersion::query()
                        ->whereKey($quotation->current_version_id)
                        ->where('tenant_id', $quotation->tenant_id)
                        ->where('quotation_id', $quotation->id)
                        ->lockForUpdate()
                        ->exists();

                    if (! $belongsToQuotation) {
                        throw new InvalidArgumentException('Quotation current version must belong to the same quotation and tenant.');
                    }
                }
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
     * @return BelongsTo<Rfq, $this>
     */
    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class);
    }

    /**
     * @return BelongsTo<Vendor, $this>
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * @return BelongsTo<RfqInvitation, $this>
     */
    public function rfqInvitation(): BelongsTo
    {
        return $this->belongsTo(RfqInvitation::class);
    }

    /**
     * @return BelongsTo<QuotationVersion, $this>
     */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(QuotationVersion::class, 'current_version_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submittedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    /**
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * @return HasMany<QuotationLineItem, $this>
     */
    public function lineItems(): HasMany
    {
        return $this->hasMany(QuotationLineItem::class)->orderBy('position');
    }

    /**
     * @return HasMany<QuotationVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(QuotationVersion::class)->orderByDesc('version_number');
    }
}
