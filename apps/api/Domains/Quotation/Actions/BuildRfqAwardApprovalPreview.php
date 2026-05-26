<?php

namespace Domains\Quotation\Actions;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Approval\Data\ApprovalPreviewData;
use Domains\Approval\Models\ApprovalPolicyVersion;
use Domains\Approval\Services\ApprovalPolicyMatcher;
use Domains\Approval\Services\ApprovalRouteBuilder;
use Domains\Approval\States\ApprovalPolicyVersionStatus;
use Domains\Approval\SubjectHandlers\RfqAwardRecommendationApprovalSubjectHandler;
use Domains\Quotation\Models\RfqAwardRecommendation;

class BuildRfqAwardApprovalPreview
{
    public function __construct(
        private readonly RfqAwardRecommendationApprovalSubjectHandler $handler,
        private readonly ApprovalPolicyMatcher $matcher,
        private readonly ApprovalRouteBuilder $routeBuilder,
    ) {}

    public function handle(Tenant $tenant, User $actor, RfqAwardRecommendation $recommendation): ApprovalPreviewData
    {
        $context = $this->handler->buildContext($recommendation);
        $match = $this->matcher->match($context, $this->tenantPolicyCandidates($tenant));
        $route = $this->routeBuilder->build(
            $context,
            $match['matchedVersion'],
            $match['matchedConditions'],
            $match['warnings'],
        );

        return new ApprovalPreviewData(
            matchedPolicy: $match['matchedPolicy'],
            matchedVersion: $match['matchedVersion'],
            matchedConditions: $match['matchedConditions'],
            stages: $route['stages'],
            warnings: $route['warnings'],
            estimatedDueAt: $route['estimatedDueAt'],
            createsTasks: false,
            context: $context->toArray(),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function tenantPolicyCandidates(Tenant $tenant): array
    {
        return ApprovalPolicyVersion::query()
            ->with('policy')
            ->where('tenant_id', $tenant->id)
            ->where('subject_type', 'rfq_award_recommendation')
            ->where('status', ApprovalPolicyVersionStatus::Published)
            ->orderByDesc('priority')
            ->orderByDesc('version_number')
            ->get()
            ->map(fn (ApprovalPolicyVersion $version): array => [
                'matchedPolicy' => [
                    'id' => (string) $version->approval_policy_id,
                    'tenantId' => (string) $version->tenant_id,
                    'name' => $version->policy?->name ?? 'Approval policy',
                    'subjectType' => $version->subject_type,
                    'status' => $version->policy?->status->value ?? 'draft',
                ],
                'matchedVersion' => [
                    'id' => (string) $version->id,
                    'tenantId' => (string) $version->tenant_id,
                    'policyId' => (string) $version->approval_policy_id,
                    'versionNumber' => $version->version_number,
                    'status' => $version->status->value,
                    'priority' => $version->priority,
                    'rules' => $version->rules ?? [],
                    'routeTemplate' => $version->route_template ?? ['stages' => []],
                    'slaRules' => $version->sla_rules ?? [],
                ],
                'priority' => $version->priority,
                'rules' => $version->rules ?? [],
                'routeTemplate' => $version->route_template ?? ['stages' => []],
                'slaRules' => $version->sla_rules ?? [],
            ])
            ->all();
    }
}
