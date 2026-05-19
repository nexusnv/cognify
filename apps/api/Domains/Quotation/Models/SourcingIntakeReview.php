<?php

namespace Domains\Quotation\Models;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Project\Models\ProcurementProject;
use Domains\Quotation\States\SourcingIntakeStatus;
use Domains\Quotation\States\SourcingPath;
use Domains\Requisition\Models\Requisition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SourcingIntakeReview extends Model
{
    protected $fillable = [
        'tenant_id',
        'requisition_id',
        'project_id',
        'assigned_buyer_id',
        'status',
        'sourcing_path',
        'category',
        'subcategory',
        'urgency',
        'complexity',
        'target_decision_date',
        'checklist',
        'internal_notes',
        'decision_reason',
        'clarification_message',
        'claimed_at',
        'decided_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SourcingIntakeStatus::class,
            'sourcing_path' => SourcingPath::class,
            'checklist' => 'array',
            'target_decision_date' => 'date',
            'claimed_at' => 'datetime',
            'decided_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $review): void {
            DB::transaction(function () use ($review): void {
                if ($review->requisition_id !== null) {
                    $requisition = Requisition::query()
                        ->whereKey($review->requisition_id)
                        ->lockForUpdate()
                        ->first();

                    if ($requisition !== null && (int) $requisition->tenant_id !== (int) $review->tenant_id) {
                        throw new InvalidArgumentException('Sourcing intake requisition must belong to the same tenant.');
                    }
                }

                if ($review->project_id !== null) {
                    $project = ProcurementProject::query()
                        ->whereKey($review->project_id)
                        ->lockForUpdate()
                        ->first();

                    if ($project !== null && (int) $project->tenant_id !== (int) $review->tenant_id) {
                        throw new InvalidArgumentException('Sourcing intake project must belong to the same tenant.');
                    }
                }

                if ($review->assigned_buyer_id !== null) {
                    $buyerInTenant = User::query()
                        ->whereKey($review->assigned_buyer_id)
                        ->whereHas('tenants', fn ($query) => $query->whereKey($review->tenant_id))
                        ->lockForUpdate()
                        ->exists();

                    if (! $buyerInTenant) {
                        throw new InvalidArgumentException('Assigned buyer must belong to the same tenant.');
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
     * @return BelongsTo<Requisition, $this>
     */
    public function requisition(): BelongsTo
    {
        return $this->belongsTo(Requisition::class);
    }

    /**
     * @return BelongsTo<ProcurementProject, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(ProcurementProject::class, 'project_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedBuyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_buyer_id');
    }
}
