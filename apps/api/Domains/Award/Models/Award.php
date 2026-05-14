<?php

namespace Domains\Award\Models;

use App\Tenancy\Tenant;
use Domains\Project\Models\ProcurementProject;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\Rfq;
use Domains\Vendor\Models\Vendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
            if ($award->project_id !== null) {
                $project = ProcurementProject::query()->find($award->project_id);

                if ($project !== null && (int) $project->tenant_id !== (int) $award->tenant_id) {
                    throw new InvalidArgumentException('Award project must belong to the same tenant.');
                }
            }

            if ($award->rfq_id !== null) {
                $rfq = Rfq::query()->find($award->rfq_id);

                if ($rfq !== null && (int) $rfq->tenant_id !== (int) $award->tenant_id) {
                    throw new InvalidArgumentException('Award RFQ must belong to the same tenant.');
                }
            }

            if ($award->quotation_id !== null) {
                $quotation = Quotation::query()->find($award->quotation_id);

                if ($quotation !== null && (int) $quotation->tenant_id !== (int) $award->tenant_id) {
                    throw new InvalidArgumentException('Award quotation must belong to the same tenant.');
                }
            }

            if ($award->vendor_id !== null) {
                $vendor = Vendor::query()->find($award->vendor_id);

                if ($vendor !== null && (int) $vendor->tenant_id !== (int) $award->tenant_id) {
                    throw new InvalidArgumentException('Award vendor must belong to the same tenant.');
                }
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
