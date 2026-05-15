<?php

namespace Domains\Requisition\Models;

use App\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequisitionItemSuggestion extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'category',
        'unit',
        'estimated_unit_price',
        'currency',
        'aliases',
        'active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'estimated_unit_price' => 'decimal:2',
            'aliases' => 'array',
            'active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
