<?php

namespace Domains\Requisition\Models;

use App\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequisitionTemplate extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'category',
        'defaults',
        'active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'defaults' => 'array',
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
