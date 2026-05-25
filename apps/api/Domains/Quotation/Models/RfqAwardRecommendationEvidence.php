<?php

namespace Domains\Quotation\Models;

use App\Tenancy\Tenant;
use Domains\Quotation\States\RfqAwardRecommendationEvidenceType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class RfqAwardRecommendationEvidence extends Model
{
    use HasUuids;

    protected $table = 'rfq_award_recommendation_evidence';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'tenant_id',
        'recommendation_id',
        'evidence_type',
        'evidence_id',
        'label',
    ];

    protected function casts(): array
    {
        return [
            'evidence_type' => RfqAwardRecommendationEvidenceType::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $evidence): void {
            $recommendation = RfqAwardRecommendation::query()
                ->whereKey($evidence->recommendation_id)
                ->first();

            if ($recommendation === null) {
                throw new InvalidArgumentException('Award recommendation evidence must reference a recommendation.');
            }

            $evidence->tenant_id ??= $recommendation->tenant_id;

            if ((int) $evidence->tenant_id !== (int) $recommendation->tenant_id) {
                throw new InvalidArgumentException('Award recommendation evidence must belong to the same tenant as the recommendation.');
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
     * @return BelongsTo<RfqAwardRecommendation, $this>
     */
    public function recommendation(): BelongsTo
    {
        return $this->belongsTo(RfqAwardRecommendation::class, 'recommendation_id');
    }
}
