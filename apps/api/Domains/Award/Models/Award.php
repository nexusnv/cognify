<?php

namespace Domains\Award\Models;

use App\Tenancy\Tenant;
use Domains\Project\Models\ProcurementProject;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\Rfq;
use Domains\Vendor\Models\Vendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
