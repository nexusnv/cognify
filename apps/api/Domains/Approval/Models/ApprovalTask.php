<?php

namespace Domains\Approval\Models;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Approval\States\ApprovalTaskStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ApprovalTask extends Model
{
    protected $fillable = [
        'tenant_id',
        'approval_instance_id',
        'approval_stage_id',
        'subject_type',
        'subject_id',
        'assignee_id',
        'original_assignee_id',
        'title',
        'status',
        'decision',
        'decision_reason',
        'requested_fields',
        'decided_by_id',
        'delegated_from_task_id',
        'escalated_from_task_id',
        'assigned_at',
        'viewed_at',
        'due_at',
        'decided_at',
        'lock_version',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'status' => ApprovalTaskStatus::class,
            'requested_fields' => 'array',
            'assigned_at' => 'datetime',
            'viewed_at' => 'datetime',
            'due_at' => 'datetime',
            'decided_at' => 'datetime',
            'lock_version' => 'integer',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $task): void {
            DB::transaction(function () use ($task): void {
                if ($task->assignee_id !== null && ($task->isDirty('assignee_id') || $task->isDirty('tenant_id'))) {
                    $belongsToTenant = User::query()
                        ->whereKey($task->assignee_id)
                        ->whereHas('tenants', fn ($query) => $query->whereKey($task->tenant_id))
                        ->lockForUpdate()
                        ->exists();

                    if (! $belongsToTenant) {
                        throw new InvalidArgumentException('Approval task assignee must belong to the same tenant.');
                    }
                }

                if (! $task->isDirty('subject_type') && ! $task->isDirty('subject_id') && ! $task->isDirty('tenant_id')) {
                    return;
                }

                if (! is_string($task->subject_type) || ! class_exists($task->subject_type)) {
                    return;
                }

                if (! is_subclass_of($task->subject_type, Model::class)) {
                    throw new InvalidArgumentException('Approval task subject type must be an Eloquent model.');
                }

                /** @var Model|null $subject */
                $subject = $task->subject_type::query()
                    ->whereKey($task->subject_id)
                    ->lockForUpdate()
                    ->first();

                if ($subject !== null && isset($subject->tenant_id) && (int) $subject->tenant_id !== (int) $task->tenant_id) {
                    throw new InvalidArgumentException('Approval task subject must belong to the same tenant.');
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
     * @return BelongsTo<User, $this>
     */
    public function instance(): BelongsTo
    {
        return $this->belongsTo(ApprovalInstance::class, 'approval_instance_id');
    }

    /**
     * @return BelongsTo<ApprovalStage, $this>
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(ApprovalStage::class, 'approval_stage_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function originalAssignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'original_assignee_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
