<?php

namespace Domains\Quotation\Models;

use App\Tenancy\Tenant;
use Domains\Requisition\Models\Requisition;
use Domains\Project\Models\ProcurementProject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
