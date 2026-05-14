<?php

namespace Domains\Project\Models;

use App\Models\User;
use App\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class ProcurementProject extends Model
{
    protected $fillable = [
        'tenant_id',
        'owner_id',
        'number',
        'name',
        'status',
        'budget_amount',
        'currency',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'budget_amount' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $project): void {
            if ($project->owner_id === null) {
                return;
            }

            $belongsToTenant = User::query()
                ->whereKey($project->owner_id)
                ->whereHas('tenants', fn ($query) => $query->whereKey($project->tenant_id))
                ->exists();

            if (! $belongsToTenant) {
                throw new InvalidArgumentException('Project owner must belong to the same tenant.');
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
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
