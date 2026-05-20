<?php

namespace Domains\Quotation\Models;

use App\Tenancy\Tenant;
use App\Models\User;
use Domains\Attachment\Models\Attachment;
use Domains\Quotation\States\QuotationStatus;
use Domains\Quotation\States\QuotationSubmissionSource;
use Domains\Quotation\Models\RfqInvitation;
use Domains\Quotation\Models\Rfq;
use Domains\Vendor\Models\Vendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'number',
        'status',
        'submission_source',
        'submitted_at',
        'submitted_by_user_id',
        'submitted_by_vendor_contact',
        'file_count',
        'latest_received_at',
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
            'file_count' => 'integer',
            'latest_received_at' => 'immutable_datetime',
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
}
