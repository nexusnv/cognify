<?php

namespace Domains\Approval\Actions;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Approval\Data\ApprovalContextData;
use Domains\Approval\Data\ApprovalPreviewData;
use Domains\Approval\Services\ApprovalPolicyMatcher;
use Domains\Approval\Services\ApprovalRouteBuilder;

class PreviewApprovalPolicy
{
    public function __construct(
        private readonly ApprovalPolicyMatcher $matcher,
        private readonly ApprovalRouteBuilder $routeBuilder,
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $candidates
     */
    public function handle(Tenant $tenant, User $actor, ApprovalContextData $context, array $candidates): ApprovalPreviewData
    {
        $match = $this->matcher->match($context, $candidates, allowUnmatchedPolicyFallback: true);
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
        );
    }
}
