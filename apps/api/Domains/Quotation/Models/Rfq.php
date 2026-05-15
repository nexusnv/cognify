<?php

namespace Domains\Quotation\Models;

use App\Tenancy\Tenant;
use Domains\Project\Models\ProcurementProject;
use Domains\Requisition\Models\Requisition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class Rfq extends Model
{
    protected $table = 'rfqs';

    protected $fillable = [
        'tenant_id',
        'project_id',
        'requisition_id',
        'number',
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
        static::saving(function (self $rfq): void {
            DB::transaction(function () use ($rfq): void {
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

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
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
}
