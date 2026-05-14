<?php

namespace Domains\Approval\Models;

use App\Models\User;
use App\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
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
            if ($task->approver_id !== null) {
                $belongsToTenant = User::query()
                    ->whereKey($task->approver_id)
                    ->whereHas('tenants', fn ($query) => $query->whereKey($task->tenant_id))
                    ->exists();

                if (! $belongsToTenant) {
                    throw new InvalidArgumentException('Approval task approver must belong to the same tenant.');
                }
            }

            if (! is_string($task->subject_type) || ! class_exists($task->subject_type)) {
                return;
            }

            $subject = $task->subject_type::query()->find($task->subject_id);

            if ($subject !== null && isset($subject->tenant_id) && (int) $subject->tenant_id !== (int) $task->tenant_id) {
                throw new InvalidArgumentException('Approval task subject must belong to the same tenant.');
            }
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
