<?php

namespace Domains\Quotation\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\Models\QuotationScoringTemplate;
use Domains\Quotation\Models\QuotationScoringTemplateCriterion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UpdateQuotationScoringTemplate
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    /**
     * @param array<string, mixed> $data
     */
    public function handle(Tenant $tenant, User $actor, QuotationScoringTemplate $template, array $data): QuotationScoringTemplate
    {
        Gate::forUser($actor)->authorize('update', $template);
        $criteria = $this->criteriaPayload($data);

        return DB::transaction(function () use ($tenant, $actor, $template, $data, $criteria): QuotationScoringTemplate {
            $lockedTemplate = QuotationScoringTemplate::query()
                ->where('tenant_id', $tenant->id)
                ->whereKey($template->id)
                ->lockForUpdate()
                ->firstOrFail();

            $before = $this->snapshot($lockedTemplate->load('criteria'));

            $lockedTemplate->forceFill([
                'name' => trim((string) $data['name']),
                'description' => $data['description'] ?? null,
                'updated_by_user_id' => $actor->id,
            ])->save();

            $this->replaceCriteriaForFutureScorecards($tenant, $lockedTemplate, $criteria);

            $lockedTemplate = $lockedTemplate->refresh()->load('criteria');

            $this->auditRecorder->record(new AuditEventData(
                tenant: $tenant,
                actor: $actor,
                action: 'quotation_scoring_template.updated',
                subject: $lockedTemplate,
                metadata: ['templateId' => (string) $lockedTemplate->id],
                before: $before,
                after: $this->snapshot($lockedTemplate),
                subjectDisplay: $lockedTemplate->name,
            ));

            return $lockedTemplate;
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

        return array_values($criteria);
    }

    /**
     * @param array<int, array<string, mixed>> $criteria
     */
    private function replaceCriteriaForFutureScorecards(Tenant $tenant, QuotationScoringTemplate $template, array $criteria): void
    {
        $existingCriteria = $template->criteria()
            ->withCount('scorecardCriteria')
            ->lockForUpdate()
            ->get()
            ->keyBy('display_order');
        $incomingOrders = [];

        foreach ($criteria as $criterion) {
            $record = $this->criteriaRecord($tenant, $criterion);
            $displayOrder = (int) $record['display_order'];
            $incomingOrders[] = $displayOrder;

            /** @var QuotationScoringTemplateCriterion|null $existing */
            $existing = $existingCriteria->get($displayOrder);
            if ($existing !== null) {
                $existing->forceFill($record)->save();

                continue;
            }

            $template->criteria()->create($record);
        }

        $template->criteria()
            ->whereNotIn('display_order', $incomingOrders)
            ->whereDoesntHave('scorecardCriteria')
            ->delete();
    }

    /**
     * @param array<string, mixed> $criterion
     * @return array<string, mixed>
     */
    private function criteriaRecord(Tenant $tenant, array $criterion): array
    {
        return [
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
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(QuotationScoringTemplate $template): array
    {
        return [
            'name' => $template->name,
            'description' => $template->description,
            'criteria' => $template->criteria
                ->map(static fn ($criterion): array => [
                    'id' => (string) $criterion->id,
                    'category' => $criterion->category?->value ?? $criterion->category,
                    'label' => $criterion->label,
                    'guidance' => $criterion->guidance,
                    'weight' => (string) $criterion->weight,
                    'maxScore' => $criterion->max_score,
                    'required' => $criterion->is_required,
                    'displayOrder' => $criterion->display_order,
                ])
                ->values()
                ->all(),
        ];
    }
}
