<?php

namespace Domains\Approval\Models;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Approval\States\ApprovalPolicyVersionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalPolicyVersion extends Model
{
    protected $fillable = [
        'approval_policy_id',
        'tenant_id',
        'subject_type',
        'version_number',
        'status',
        'effective_from',
        'effective_until',
        'priority',
        'rules',
        'route_template',
        'sla_rules',
        'published_by',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ApprovalPolicyVersionStatus::class,
            'effective_from' => 'datetime',
            'effective_until' => 'datetime',
            'published_at' => 'datetime',
            'rules' => 'array',
            'route_template' => 'array',
            'sla_rules' => 'array',
        ];
    }

    /**
     * @return BelongsTo<ApprovalPolicy, $this>
     */
    public function policy(): BelongsTo
    {
        return $this->belongsTo(ApprovalPolicy::class, 'approval_policy_id');
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
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
