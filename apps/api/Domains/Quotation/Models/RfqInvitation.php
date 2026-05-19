<?php

namespace Domains\Quotation\Models;

use App\Tenancy\Tenant;
use Domains\Quotation\States\RfqInvitationStatus;
use Domains\Vendor\Models\Vendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RfqInvitation extends Model
{
    protected $fillable = [
        'tenant_id',
        'rfq_id',
        'vendor_id',
        'status',
        'contact_name',
        'contact_email',
        'message',
        'response_due_at',
        'sent_at',
        'acknowledged_at',
        'declined_at',
        'expired_at',
        'cancelled_at',
        'cancel_reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => RfqInvitationStatus::class,
            'response_due_at' => 'datetime',
            'sent_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'declined_at' => 'datetime',
            'expired_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $invitation): void {
            DB::transaction(function () use ($invitation): void {
                if ($invitation->rfq_id !== null && ($invitation->isDirty('rfq_id') || $invitation->isDirty('tenant_id'))) {
                    $rfq = Rfq::query()
                        ->whereKey($invitation->rfq_id)
                        ->lockForUpdate()
                        ->first();

                    if ($rfq !== null && (int) $rfq->tenant_id !== (int) $invitation->tenant_id) {
                        throw new InvalidArgumentException('RFQ invitation RFQ must belong to the same tenant.');
                    }
                }

                if ($invitation->vendor_id !== null && ($invitation->isDirty('vendor_id') || $invitation->isDirty('tenant_id'))) {
                    $vendor = Vendor::query()
                        ->whereKey($invitation->vendor_id)
                        ->lockForUpdate()
                        ->first();

                    if ($vendor !== null && (int) $vendor->tenant_id !== (int) $invitation->tenant_id) {
                        throw new InvalidArgumentException('RFQ invitation vendor must belong to the same tenant.');
                    }
                }
            });
        });
    }

    public function statusState(): RfqInvitationStatus
    {
        return $this->status instanceof RfqInvitationStatus
            ? $this->status
            : RfqInvitationStatus::from((string) $this->getAttribute('status'));
    }

    public function isActive(): bool
    {
        return $this->statusState()->isActive();
    }

    public function isTerminal(): bool
    {
        return $this->statusState()->isTerminal();
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
}
