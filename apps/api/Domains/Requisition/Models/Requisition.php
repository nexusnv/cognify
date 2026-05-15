<?php

namespace Domains\Requisition\Models;

use Domains\Attachment\Models\Attachment;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Requisition\States\RequisitionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

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
        'lock_version',
        'submitted_at',
        'changes_requested_at',
        'changes_requested_by_id',
        'change_request_reason',
        'change_request_fields',
        'withdrawn_at',
        'withdrawn_by_id',
        'withdrawal_reason',
        'cancelled_at',
        'cancelled_by_id',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'needed_by_date' => 'date',
            'submitted_at' => 'datetime',
            'changes_requested_at' => 'datetime',
            'change_request_fields' => 'array',
            'withdrawn_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'lock_version' => 'integer',
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

    /**
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function changesRequestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changes_requested_by_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function withdrawnBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'withdrawn_by_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_id');
    }
}
