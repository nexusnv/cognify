<?php

namespace Domains\Approval\Actions;

use App\Audit\AuditEvent;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Approval\Models\ApprovalPolicy;
use Domains\Approval\Models\ApprovalPolicyVersion;
use Domains\Approval\States\ApprovalPolicyStatus;
use Domains\Approval\States\ApprovalPolicyVersionStatus;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class PublishApprovalPolicyVersion
{
    public function handle(Tenant $tenant, User $actor, ApprovalPolicyVersion $version): ApprovalPolicyVersion
    {
        if ((int) $version->tenant_id !== (int) $tenant->id) {
            abort(404);
        }

        if ($version->status !== ApprovalPolicyVersionStatus::Draft) {
            throw new ConflictHttpException('Only draft approval policy versions can be published.');
        }

        return DB::transaction(function () use ($tenant, $actor, $version): ApprovalPolicyVersion {
            $version->load('policy');
            $now = now();

            $retiredPolicyIds = ApprovalPolicyVersion::query()
                ->where('tenant_id', $tenant->id)
                ->where('subject_type', $version->subject_type)
                ->where('status', ApprovalPolicyVersionStatus::Published->value)
                ->where('id', '!=', $version->id)
                ->pluck('approval_policy_id')
                ->unique()
                ->values();

            ApprovalPolicyVersion::query()
                ->where('tenant_id', $tenant->id)
                ->where('subject_type', $version->subject_type)
                ->where('status', ApprovalPolicyVersionStatus::Published->value)
                ->where('id', '!=', $version->id)
                ->update([
                    'status' => ApprovalPolicyVersionStatus::Retired->value,
                    'effective_until' => $now,
                    'updated_at' => $now,
                ]);

            if ($retiredPolicyIds->isNotEmpty()) {
                ApprovalPolicy::query()
                    ->where('tenant_id', $tenant->id)
                    ->whereIn('id', $retiredPolicyIds)
                    ->where('status', ApprovalPolicyStatus::Active->value)
                    ->update([
                        'status' => ApprovalPolicyStatus::Draft->value,
                        'updated_by' => $actor->id,
                        'updated_at' => $now,
                    ]);
            }

            $version->forceFill([
                'status' => ApprovalPolicyVersionStatus::Published,
                'effective_from' => $version->effective_from ?? $now,
                'published_by' => $actor->id,
                'published_at' => $now,
            ])->save();

            $version->policy->forceFill([
                'status' => ApprovalPolicyStatus::Active,
                'updated_by' => $actor->id,
            ])->save();

            AuditEvent::query()->create([
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'event_type' => 'approval_policy.published',
                'action' => 'approval_policy.published',
                'subject_type' => ApprovalPolicy::class,
                'subject_id' => $version->approval_policy_id,
                'metadata' => [
                    'versionId' => $version->id,
                    'versionNumber' => $version->version_number,
                    'subjectType' => $version->subject_type,
                ],
                'occurred_at' => $now,
            ]);

            return $version->fresh(['policy']);
        });
    }

    public function retire(Tenant $tenant, User $actor, ApprovalPolicyVersion $version): ApprovalPolicyVersion
    {
        if ((int) $version->tenant_id !== (int) $tenant->id) {
            abort(404);
        }

        if ($version->status === ApprovalPolicyVersionStatus::Retired) {
            throw new ConflictHttpException('Approval policy version is already retired.');
        }

        DB::transaction(function () use ($tenant, $actor, $version): void {
            $version->load('policy');
            $now = now();

            $version->forceFill([
                'status' => ApprovalPolicyVersionStatus::Retired,
                'effective_until' => $now,
            ])->save();

            $hasPublishedVersions = ApprovalPolicyVersion::query()
                ->where('tenant_id', $tenant->id)
                ->where('approval_policy_id', $version->approval_policy_id)
                ->where('status', ApprovalPolicyVersionStatus::Published->value)
                ->exists();

            if (! $hasPublishedVersions) {
                $version->policy->forceFill([
                    'status' => ApprovalPolicyStatus::Draft,
                    'updated_by' => $actor->id,
                ])->save();
            }

            AuditEvent::query()->create([
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'event_type' => 'approval_policy.retired',
                'action' => 'approval_policy.retired',
                'subject_type' => ApprovalPolicy::class,
                'subject_id' => $version->approval_policy_id,
                'metadata' => ['versionId' => $version->id],
                'occurred_at' => $now,
            ]);
        });

        return $version->fresh(['policy']);
    }
}
