<?php

namespace App\Audit;

use App\Models\User;
use App\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class AuditEvent extends Model
{
    protected $fillable = [
        'event_id',
        'tenant_id',
        'actor_id',
        'event_type',
        'action',
        'subject_type',
        'subject_id',
        'subject_display',
        'metadata',
        'before',
        'after',
        'ip_address',
        'user_agent',
        'request_id',
        'occurred_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (AuditEvent $event): void {
            $event->event_id ??= (string) Str::uuid();

            if (! $event->action && ! $event->event_type) {
                throw new InvalidArgumentException('Audit events require either an action or event_type.');
            }

            // event_type is retained only for compatibility while action is the public contract name.
            $event->action ??= $event->event_type;
            $event->event_type ??= $event->action;
        });

        static::updating(function (): void {
            throw new RuntimeException('Audit events are immutable.');
        });

        static::deleting(function (): void {
            throw new RuntimeException('Audit events are immutable.');
        });
    }

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'before' => 'array',
            'after' => 'array',
            'occurred_at' => 'datetime',
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
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
