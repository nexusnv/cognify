<?php

namespace Domains\Approval\Models;

use App\Tenancy\Tenant;
use Domains\Approval\States\ApprovalStageStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalStage extends Model
{
    protected $fillable = [
        'tenant_id',
        'approval_instance_id',
        'sequence',
        'name',
        'completion_rule',
        'status',
        'activated_at',
        'completed_at',
        'due_at',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'status' => ApprovalStageStatus::class,
            'activated_at' => 'datetime',
            'completed_at' => 'datetime',
            'due_at' => 'datetime',
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
     * @return BelongsTo<ApprovalInstance, $this>
     */
    public function instance(): BelongsTo
    {
        return $this->belongsTo(ApprovalInstance::class, 'approval_instance_id');
    }

    /**
     * @return HasMany<ApprovalTask, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(ApprovalTask::class);
    }
}
