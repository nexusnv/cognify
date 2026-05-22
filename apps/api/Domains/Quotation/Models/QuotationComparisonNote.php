<?php

namespace Domains\Quotation\Models;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\States\QuotationComparisonNoteSection;
use Domains\Vendor\Models\Vendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;

class QuotationComparisonNote extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'rfq_id',
        'quotation_id',
        'vendor_id',
        'rfq_line_item_id',
        'section',
        'note',
        'created_by_user_id',
        'updated_by_user_id',
        'deleted_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'section' => QuotationComparisonNoteSection::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $note): void {
            $rfq = Rfq::query()->whereKey($note->rfq_id)->first();
            if ($rfq === null) {
                throw new InvalidArgumentException('Comparison note RFQ is required.');
            }

            $note->tenant_id ??= $rfq->tenant_id;

            if ((int) $note->tenant_id !== (int) $rfq->tenant_id) {
                throw new InvalidArgumentException('Comparison note RFQ must belong to the same tenant.');
            }

            if ($note->quotation_id !== null) {
                $quotation = Quotation::query()->whereKey($note->quotation_id)->first();
                if ($quotation === null || (int) $quotation->tenant_id !== (int) $note->tenant_id || (int) $quotation->rfq_id !== (int) $note->rfq_id) {
                    throw new InvalidArgumentException('Comparison note quotation must belong to the same RFQ and tenant.');
                }

                if ($note->vendor_id !== null && (int) $quotation->vendor_id !== (int) $note->vendor_id) {
                    throw new InvalidArgumentException('Comparison note vendor must match the selected quotation.');
                }
            }

            if ($note->vendor_id !== null) {
                $vendorIsInvited = RfqInvitation::query()
                    ->where('tenant_id', $note->tenant_id)
                    ->where('rfq_id', $note->rfq_id)
                    ->where('vendor_id', $note->vendor_id)
                    ->exists();

                if (! $vendorIsInvited) {
                    throw new InvalidArgumentException('Comparison note vendor must belong to the same RFQ and tenant.');
                }
            }

            if ($note->rfq_line_item_id !== null) {
                $lineItemIds = collect($rfq->line_items ?? [])
                    ->map(fn ($lineItem) => (string) data_get($lineItem, 'id'))
                    ->filter();

                if (! $lineItemIds->contains((string) $note->rfq_line_item_id)) {
                    throw new InvalidArgumentException('Comparison note line item must belong to the RFQ.');
                }
            }
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
     * @return BelongsTo<Quotation, $this>
     */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /**
     * @return BelongsTo<Vendor, $this>
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
