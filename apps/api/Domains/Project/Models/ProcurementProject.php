<?php

namespace Domains\Project\Models;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Project\States\ProjectStatus;
use Domains\Requisition\Models\Requisition;
use Domains\Requisition\States\RequisitionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProcurementProject extends Model
{
    protected $fillable = [
        'tenant_id',
        'owner_id',
        'number',
        'name',
        'charter',
        'status',
        'budget_amount',
        'currency',
        'department',
        'cost_center',
        'target_start_date',
        'target_completion_date',
        'cancelled_at',
        'cancelled_by_id',
        'cancellation_reason',
        'completed_at',
        'completed_by_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'budget_amount' => 'decimal:2',
            'target_start_date' => 'date',
            'target_completion_date' => 'date',
            'cancelled_at' => 'datetime',
            'completed_at' => 'datetime',
            'status' => ProjectStatus::class,
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $project): void {
            DB::transaction(function () use ($project): void {
                if ($project->owner_id === null || (! $project->isDirty('owner_id') && ! $project->isDirty('tenant_id'))) {
                    return;
                }

                $belongsToTenant = User::query()
                    ->whereKey($project->owner_id)
                    ->whereHas('tenants', fn ($query) => $query->whereKey($project->tenant_id))
                    ->lockForUpdate()
                    ->exists();

                if (! $belongsToTenant) {
                    throw new InvalidArgumentException('Project owner must belong to the same tenant.');
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
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return HasMany<Requisition, $this>
     */
    public function requisitions(): HasMany
    {
        return $this->hasMany(Requisition::class, 'project_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_id');
    }

    /**
     * @param Builder<ProcurementProject> $query
     * @return Builder<ProcurementProject>
     */
    public function scopeVisibleTo(Builder $query, User $user, ?string $role, int $tenantId): Builder
    {
        $query->where('tenant_id', $tenantId);

        if ($role === null) {
            return $query->whereRaw('1 = 0');
        }

        if (in_array($role, [TenantRole::Buyer->value, TenantRole::Admin->value], true)) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($user, $role, $tenantId): void {
            $query->where('owner_id', $user->id)
                ->orWhereExists(function ($subquery) use ($user, $role, $tenantId): void {
                    $subquery->selectRaw('1')
                        ->from('requisitions')
                        ->whereRaw('requisitions.project_id = CAST(procurement_projects.id AS TEXT)')
                        ->where('requisitions.tenant_id', $tenantId)
                        ->where(function ($visibleQuery) use ($user, $role): void {
                            if ($role === TenantRole::Requester->value) {
                                $visibleQuery->where('requester_id', $user->id);

                                return;
                            }

                            if ($role === TenantRole::Approver->value) {
                                $visibleQuery->where('status', RequisitionStatus::Submitted->value);

                                return;
                            }

                            $visibleQuery->whereRaw('1 = 0');
                        });
                });
        });
    }
}
