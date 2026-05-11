<?php

namespace Domains\Requisition\Models;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Requisition\States\RequisitionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Requisition extends Model
{
    protected $fillable = [
        'tenant_id',
        'requester_id',
        'number',
        'title',
        'business_justification',
        'needed_by_date',
        'department',
        'project_id',
        'cost_center',
        'delivery_location',
        'currency',
        'status',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'needed_by_date' => 'date',
            'submitted_at' => 'datetime',
            'status' => RequisitionStatus::class,
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
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /**
     * @return HasMany<RequisitionLineItem, $this>
     */
    public function lineItems(): HasMany
    {
        return $this->hasMany(RequisitionLineItem::class);
    }
}
