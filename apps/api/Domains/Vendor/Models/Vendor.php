<?php

namespace Domains\Vendor\Models;

use App\Tenancy\Tenant;
use Domains\Quotation\Models\RfqInvitation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendor extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'status',
        'category',
        'risk_rating',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
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
     * @return HasMany<RfqInvitation, $this>
     */
    public function rfqInvitations(): HasMany
    {
        return $this->hasMany(RfqInvitation::class);
    }
}
