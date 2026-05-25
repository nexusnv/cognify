<?php

namespace Domains\Quotation\Http\Resources;

use Domains\Quotation\Models\QuotationScoringTemplate;
use Domains\Quotation\Models\QuotationScoringTemplateCriterion;
use Domains\Quotation\Models\RfqScorecard;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuotationScoringTemplateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var QuotationScoringTemplate $template */
        $template = $this->resource;
        $criteria = $template->relationLoaded('criteria')
            ? $template->criteria
            : $template->criteria()->get();

        $payload = [
            'id' => (string) $template->id,
            'name' => $template->name,
            'description' => $template->description,
            'active' => (bool) $template->is_active,
            'criteria' => $criteria
                ->map(fn (QuotationScoringTemplateCriterion $criterion): array => $this->criterion($criterion))
                ->values()
                ->all(),
            'usageCount' => $this->usageCountValue($template),
            'permissions' => [
                'canView' => $request->user()?->can('view', $template) ?? false,
                'canUpdate' => $request->user()?->can('update', $template) ?? false,
                'canDeactivate' => $request->user()?->can('deactivate', $template) ?? false,
            ],
        ];

        $payload['template'] = [
            'id' => $payload['id'],
            'name' => $payload['name'],
            'description' => $payload['description'],
            'active' => $payload['active'],
            'usageCount' => $payload['usageCount'],
            'permissions' => $payload['permissions'],
        ];

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function criterion(QuotationScoringTemplateCriterion $criterion): array
    {
        return [
            'id' => (string) $criterion->id,
            'category' => $criterion->category?->value ?? $criterion->category,
            'label' => $criterion->label,
            'guidance' => $criterion->guidance,
            'weight' => (string) $criterion->weight,
            'maxScore' => $criterion->max_score,
            'required' => (bool) $criterion->is_required,
            'displayOrder' => $criterion->display_order,
        ];
    }

    private function usageCount(QuotationScoringTemplate $template): int
    {
        return RfqScorecard::query()
            ->where('tenant_id', $template->tenant_id)
            ->where('template_id', $template->id)
            ->count();
    }

    private function usageCountValue(QuotationScoringTemplate $template): int
    {
        $preloaded = $template->getAttribute('usage_count');

        return $preloaded !== null ? (int) $preloaded : $this->usageCount($template);
    }
}
