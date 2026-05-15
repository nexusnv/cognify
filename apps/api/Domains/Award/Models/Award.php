<?php

namespace Domains\Award\Models;

use App\Tenancy\Tenant;
use Domains\Project\Models\ProcurementProject;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\Rfq;
use Domains\Vendor\Models\Vendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class Award extends Model
{
    protected $fillable = [
        'tenant_id',
        'project_id',
        'rfq_id',
        'quotation_id',
        'vendor_id',
        'number',
        'status',
        'total_amount',
        'currency',
        'decided_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'decided_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $award): void {
            DB::transaction(function () use ($award): void {
                if ($award->project_id !== null && ($award->isDirty('project_id') || $award->isDirty('tenant_id'))) {
                    $project = ProcurementProject::query()
                        ->whereKey($award->project_id)
                        ->lockForUpdate()
                        ->first();

                    if ($project !== null && (int) $project->tenant_id !== (int) $award->tenant_id) {
                        throw new InvalidArgumentException('Award project must belong to the same tenant.');
                    }
                }

                if ($award->rfq_id !== null && ($award->isDirty('rfq_id') || $award->isDirty('tenant_id'))) {
                    $rfq = Rfq::query()
                        ->whereKey($award->rfq_id)
                        ->lockForUpdate()
                        ->first();

                    if ($rfq !== null && (int) $rfq->tenant_id !== (int) $award->tenant_id) {
                        throw new InvalidArgumentException('Award RFQ must belong to the same tenant.');
                    }
                }

                if ($award->quotation_id !== null && ($award->isDirty('quotation_id') || $award->isDirty('tenant_id'))) {
                    $quotation = Quotation::query()
                        ->whereKey($award->quotation_id)
                        ->lockForUpdate()
                        ->first();

                    if ($quotation !== null && (int) $quotation->tenant_id !== (int) $award->tenant_id) {
                        throw new InvalidArgumentException('Award quotation must belong to the same tenant.');
                    }
                }

                if ($award->vendor_id !== null && ($award->isDirty('vendor_id') || $award->isDirty('tenant_id'))) {
                    $vendor = Vendor::query()
                        ->whereKey($award->vendor_id)
                        ->lockForUpdate()
                        ->first();

                    if ($vendor !== null && (int) $vendor->tenant_id !== (int) $award->tenant_id) {
                        throw new InvalidArgumentException('Award vendor must belong to the same tenant.');
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
     * @return BelongsTo<Rfq, $this>
     */
    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class);
    }

    /**
     * @return BelongsTo<Quotation, $this>
     */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /**
     * @return BelongsTo<Vendor, $this>
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
