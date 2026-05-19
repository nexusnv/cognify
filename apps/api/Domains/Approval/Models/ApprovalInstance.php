<?php

namespace Domains\Approval\Models;

use App\Tenancy\Tenant;
use Domains\Approval\States\ApprovalInstanceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ApprovalInstance extends Model
{
    protected $fillable = [
        'tenant_id',
        'subject_type',
        'subject_id',
        'approval_policy_version_id',
        'status',
        'current_stage_sequence',
        'matched_context',
        'matched_explanation',
        'started_at',
        'completed_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ApprovalInstanceStatus::class,
            'current_stage_sequence' => 'integer',
            'matched_context' => 'array',
            'matched_explanation' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
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
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<ApprovalPolicyVersion, $this>
     */
    public function policyVersion(): BelongsTo
    {
        return $this->belongsTo(ApprovalPolicyVersion::class, 'approval_policy_version_id');
    }

    /**
     * @return HasMany<ApprovalStage, $this>
     */
    public function stages(): HasMany
    {
        return $this->hasMany(ApprovalStage::class)->orderBy('sequence');
    }

    /**
     * @return HasMany<ApprovalTask, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(ApprovalTask::class);
    }
}
