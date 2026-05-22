<?php

namespace Domains\Quotation\Models;

use App\Tenancy\Tenant;
use Domains\Project\Models\ProcurementProject;
use Domains\Quotation\Models\RfqInvitation;
use Domains\Quotation\States\RfqStatus;
use Domains\Requisition\Models\Requisition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class Rfq extends Model
{
    protected $table = 'rfqs';

    protected $fillable = [
        'tenant_id',
        'sourcing_intake_review_id',
        'project_id',
        'requisition_id',
        'number',
        'title',
        'status',
        'due_at',
        'response_due_at',
        'scope_summary',
        'response_instructions',
        'required_documents',
        'line_items',
        'evaluation_notes',
        'internal_notes',
        'cancel_reason',
        'cancelled_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'response_due_at' => 'datetime',
            'required_documents' => 'array',
            'line_items' => 'array',
            'cancelled_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $rfq): void {
            DB::transaction(function () use ($rfq): void {
                if ($rfq->sourcing_intake_review_id !== null && ($rfq->isDirty('sourcing_intake_review_id') || $rfq->isDirty('tenant_id'))) {
                    $review = SourcingIntakeReview::query()
                        ->whereKey($rfq->sourcing_intake_review_id)
                        ->lockForUpdate()
                        ->first();

                    if ($review !== null && (int) $review->tenant_id !== (int) $rfq->tenant_id) {
                        throw new InvalidArgumentException('RFQ sourcing intake review must belong to the same tenant.');
                    }
                }

                if ($rfq->project_id !== null && ($rfq->isDirty('project_id') || $rfq->isDirty('tenant_id'))) {
                    $project = ProcurementProject::query()
                        ->whereKey($rfq->project_id)
                        ->lockForUpdate()
                        ->first();

                    if ($project !== null && (int) $project->tenant_id !== (int) $rfq->tenant_id) {
                        throw new InvalidArgumentException('RFQ project must belong to the same tenant.');
                    }
                }

                if ($rfq->requisition_id !== null && ($rfq->isDirty('requisition_id') || $rfq->isDirty('tenant_id'))) {
                    $requisition = Requisition::query()
                        ->whereKey($rfq->requisition_id)
                        ->lockForUpdate()
                        ->first();

                    if ($requisition !== null && (int) $requisition->tenant_id !== (int) $rfq->tenant_id) {
                        throw new InvalidArgumentException('RFQ requisition must belong to the same tenant.');
                    }
                }
            });
        });
    }

    public function statusState(): RfqStatus
    {
        return RfqStatus::from((string) $this->getAttribute('status'));
    }

    public function isEditable(): bool
    {
        return $this->statusState()->isEditable();
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<SourcingIntakeReview, $this>
     */
    public function sourcingIntakeReview(): BelongsTo
    {
        return $this->belongsTo(SourcingIntakeReview::class, 'sourcing_intake_review_id');
    }

    /**
     * @return BelongsTo<ProcurementProject, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(ProcurementProject::class, 'project_id');
    }

    /**
     * @return BelongsTo<Requisition, $this>
     */
    public function requisition(): BelongsTo
    {
        return $this->belongsTo(Requisition::class);
    }

    /**
     * @return HasMany<RfqInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(RfqInvitation::class);
    }

    /**
     * @return HasMany<QuotationComparisonNote, $this>
     */
    public function comparisonNotes(): HasMany
    {
        return $this->hasMany(QuotationComparisonNote::class);
    }
}
