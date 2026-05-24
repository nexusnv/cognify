<?php

namespace Domains\Quotation\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\Models\QuotationScoringTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CreateQuotationScoringTemplate
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    /**
     * @param array<string, mixed> $data
     */
    public function handle(Tenant $tenant, User $actor, array $data): QuotationScoringTemplate
    {
        Gate::forUser($actor)->authorize('create', QuotationScoringTemplate::class);
        $criteria = $this->criteriaPayload($data);

        return DB::transaction(function () use ($tenant, $actor, $data, $criteria): QuotationScoringTemplate {
            $template = QuotationScoringTemplate::query()->create([
                'tenant_id' => $tenant->id,
                'name' => trim((string) $data['name']),
                'description' => $data['description'] ?? null,
                'is_active' => true,
                'created_by_user_id' => $actor->id,
            ]);

            $template->criteria()->createMany($this->criteriaRecords($tenant, $criteria));

            $template = $template->refresh()->load('criteria');

            $this->auditRecorder->record(new AuditEventData(
                tenant: $tenant,
                actor: $actor,
                action: 'quotation_scoring_template.created',
                subject: $template,
                metadata: [
                    'templateId' => (string) $template->id,
                    'criteriaCount' => $template->criteria->count(),
                ],
                subjectDisplay: $template->name,
            ));

            return $template;
        });
    }

    /**
     * @param array<string, mixed> $data
     * @return array<int, array<string, mixed>>
     */
    private function criteriaPayload(array $data): array
    {
        $criteria = $data['criteria'] ?? null;

        if (! is_array($criteria) || $criteria === []) {
            throw ValidationException::withMessages([
                'criteria' => ['At least one scoring criterion is required.'],
            ]);
        }

        $displayOrders = collect($criteria)->pluck('displayOrder');
        if ($displayOrders->count() !== $displayOrders->unique()->count()) {
            throw ValidationException::withMessages([
                'criteria' => ['Criterion display orders must be unique.'],
            ]);
        }

        return array_values($criteria);
    }

    /**
     * @param array<int, array<string, mixed>> $criteria
     * @return array<int, array<string, mixed>>
     */
    private function criteriaRecords(Tenant $tenant, array $criteria): array
    {
        return array_map(
            static fn (array $criterion): array => [
                'tenant_id' => $tenant->id,
                'category' => $criterion['category'],
                'label' => trim((string) $criterion['label']),
                'guidance' => array_key_exists('guidance', $criterion) && $criterion['guidance'] !== null
                    ? trim((string) $criterion['guidance'])
                    : null,
                'weight' => $criterion['weight'],
                'max_score' => $criterion['maxScore'],
                'is_required' => (bool) ($criterion['required'] ?? false),
                'display_order' => $criterion['displayOrder'],
            ],
            $criteria,
        );
    }
}
