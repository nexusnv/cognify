<?php

namespace Domains\Quotation\Models;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\States\QuotationScoringCriterionCategory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuotationScoringTemplate extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'is_active',
        'created_by_user_id',
        'updated_by_user_id',
        'deactivated_by_user_id',
        'deactivated_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'deactivated_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function deactivatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deactivated_by_user_id');
    }

    /**
     * @return HasMany<QuotationScoringTemplateCriterion, $this>
     */
    public function criteria(): HasMany
    {
        return $this->hasMany(QuotationScoringTemplateCriterion::class)->orderBy('display_order');
    }
}
