<?php

namespace Domains\Approval\Models;

use App\Models\User;
use App\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ApprovalTask extends Model
{
    protected $fillable = [
        'tenant_id',
        'approver_id',
        'subject_type',
        'subject_id',
        'title',
        'status',
        'due_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $task): void {
            DB::transaction(function () use ($task): void {
                if ($task->approver_id !== null && ($task->isDirty('approver_id') || $task->isDirty('tenant_id'))) {
                    $belongsToTenant = User::query()
                        ->whereKey($task->approver_id)
                        ->whereHas('tenants', fn ($query) => $query->whereKey($task->tenant_id))
                        ->lockForUpdate()
                        ->exists();

                    if (! $belongsToTenant) {
                        throw new InvalidArgumentException('Approval task approver must belong to the same tenant.');
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
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
