<?php

namespace Domains\Quotation\Models;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\States\QuotationStatus;
use Domains\Quotation\States\QuotationSubmissionSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class QuotationVersion extends Model
{
    protected $fillable = [
        'tenant_id',
        'quotation_id',
        'version_number',
        'status',
        'submission_source',
        'submitted_at',
        'submitted_by_user_id',
        'submitted_by_vendor_contact',
        'is_current',
        'superseded_at',
        'quotation_reference',
        'quoted_at',
        'valid_until',
        'currency',
        'subtotal_amount',
        'tax_amount',
        'freight_amount',
        'discount_amount',
        'total_amount',
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
        'attachment_snapshots',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => QuotationStatus::class,
            'submission_source' => QuotationSubmissionSource::class,
            'submitted_at' => 'immutable_datetime',
            'submitted_by_vendor_contact' => 'array',
            'is_current' => 'boolean',
            'superseded_at' => 'immutable_datetime',
            'quoted_at' => 'immutable_date',
            'valid_until' => 'immutable_date',
            'subtotal_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'freight_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'lead_time_days' => 'integer',
            'manual_entry_complete' => 'boolean',
            'manual_entry_missing_fields' => 'array',
            'attachment_snapshots' => 'array',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $version): void {
            DB::transaction(function () use ($version): void {
                if ($version->tenant_id === null && $version->quotation_id !== null) {
                    $version->tenant_id = Quotation::query()
                        ->whereKey($version->quotation_id)
                        ->value('tenant_id');
                }

                $belongsToTenant = Quotation::query()
                    ->whereKey($version->quotation_id)
                    ->where('tenant_id', $version->tenant_id)
                    ->lockForUpdate()
                    ->exists();

                if (! $belongsToTenant) {
                    throw new InvalidArgumentException('Quotation version must belong to the same tenant as the quotation.');
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
     * @return BelongsTo<Quotation, $this>
     */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function submittedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    /**
     * @return HasMany<QuotationVersionLineItem, $this>
     */
    public function lineItems(): HasMany
    {
        return $this->hasMany(QuotationVersionLineItem::class)->orderBy('position');
    }
}
