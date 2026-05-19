<?php

namespace Domains\Approval\Actions;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Approval\Models\ApprovalPolicy;
use Domains\Approval\Models\ApprovalPolicyVersion;
use Domains\Approval\States\ApprovalPolicyStatus;
use Domains\Approval\States\ApprovalPolicyVersionStatus;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CreateApprovalPolicyVersionDraft
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Tenant $tenant, User $actor, ApprovalPolicy $policy, array $data): ApprovalPolicyVersion
    {
        if ((int) $policy->tenant_id !== (int) $tenant->id) {
            abort(404);
        }

        return DB::transaction(function () use ($tenant, $actor, $policy, $data): ApprovalPolicyVersion {
            $lockedPolicy = ApprovalPolicy::query()
                ->whereKey($policy->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $lockedPolicy->tenant_id !== (int) $tenant->id) {
                abort(404);
            }

            if ($lockedPolicy->status === ApprovalPolicyStatus::Archived) {
                throw new ConflictHttpException('Archived approval policies cannot receive new versions.');
            }

            $nextVersionNumber = ((int) $lockedPolicy->versions()->lockForUpdate()->max('version_number')) + 1;

            $version = ApprovalPolicyVersion::query()->create([
                'approval_policy_id' => $lockedPolicy->id,
                'tenant_id' => $tenant->id,
                'subject_type' => $lockedPolicy->subject_type,
                'version_number' => $nextVersionNumber,
                'status' => ApprovalPolicyVersionStatus::Draft,
                'priority' => (int) ($data['priority'] ?? 100),
                'rules' => $data['rules'],
                'route_template' => $data['routeTemplate'],
                'sla_rules' => $data['slaRules'] ?? [],
            ]);

            $lockedPolicy->forceFill(['updated_by' => $actor->id])->save();

            return $version;
        });
    }
}
