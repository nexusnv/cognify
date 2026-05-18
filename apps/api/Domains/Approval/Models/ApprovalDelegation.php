<?php

namespace Domains\Approval\Models;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Approval\States\ApprovalDelegationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ApprovalDelegation extends Model
{
    protected $fillable = [
        'tenant_id',
        'delegator_id',
        'delegate_id',
        'scope',
        'starts_at',
        'ends_at',
        'status',
        'reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ApprovalDelegationStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $delegation): void {
            DB::transaction(function () use ($delegation): void {
                foreach (['delegator_id', 'delegate_id', 'created_by'] as $field) {
                    if ($delegation->{$field} === null || (! $delegation->isDirty($field) && ! $delegation->isDirty('tenant_id'))) {
                        continue;
                    }

                    $belongsToTenant = User::query()
                        ->whereKey($delegation->{$field})
                        ->whereHas('tenants', fn ($query) => $query->whereKey($delegation->tenant_id))
                        ->lockForUpdate()
                        ->exists();

                    if (! $belongsToTenant) {
                        throw new InvalidArgumentException('Approval delegation users must belong to the same tenant.');
                    }
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
    public function delegator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegator_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function delegate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegate_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
