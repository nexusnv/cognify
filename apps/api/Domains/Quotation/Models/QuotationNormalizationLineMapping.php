<?php

namespace Domains\Quotation\Models;

use App\Tenancy\Tenant;
use Domains\Quotation\States\QuotationNormalizationMappingType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class QuotationNormalizationLineMapping extends Model
{
    protected $fillable = [
        'tenant_id',
        'quotation_normalization_line_group_id',
        'rfq_line_item_id',
        'quotation_version_line_item_id',
        'mapping_type',
        'quantity',
        'unit',
        'unit_price',
        'line_total',
        'buyer_note',
    ];

    protected function casts(): array
    {
        return [
            'mapping_type' => QuotationNormalizationMappingType::class,
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $mapping): void {
            if ($mapping->exists
                && ! $mapping->isDirty('tenant_id')
                && ! $mapping->isDirty('quotation_normalization_line_group_id')
                && ! $mapping->isDirty('quotation_version_line_item_id')) {
                return;
            }

            DB::transaction(function () use ($mapping): void {
                $lineGroup = QuotationNormalizationLineGroup::query()
                    ->whereKey($mapping->quotation_normalization_line_group_id)
                    ->lockForUpdate()
                    ->first();

                if ($lineGroup === null) {
                    throw new InvalidArgumentException('Quotation normalization line mapping must belong to the same tenant as the line group.');
                }

                if ($mapping->tenant_id === null) {
                    $mapping->tenant_id = $lineGroup->tenant_id;
                }

                if ($mapping->tenant_id !== $lineGroup->tenant_id) {
                    throw new InvalidArgumentException('Quotation normalization line mapping must belong to the same tenant as the line group.');
                }

                if ($mapping->quotation_version_line_item_id !== null) {
                    $lineItem = QuotationVersionLineItem::query()
                        ->whereKey($mapping->quotation_version_line_item_id)
                        ->lockForUpdate()
                        ->first();

                    if ($lineItem === null || $lineItem->tenant_id !== $mapping->tenant_id) {
                        throw new InvalidArgumentException('Quotation normalization line mapping version line item must belong to the same tenant.');
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
     * @return BelongsTo<QuotationNormalizationLineGroup, $this>
     */
    public function lineGroup(): BelongsTo
    {
        return $this->belongsTo(QuotationNormalizationLineGroup::class, 'quotation_normalization_line_group_id');
    }

    /**
     * @return BelongsTo<QuotationVersionLineItem, $this>
     */
    public function quotationVersionLineItem(): BelongsTo
    {
        return $this->belongsTo(QuotationVersionLineItem::class);
    }
}
