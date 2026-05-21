<?php

namespace Domains\Quotation\Models;

use App\Tenancy\Tenant;
use Domains\Quotation\States\QuotationNormalizationMappingType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
