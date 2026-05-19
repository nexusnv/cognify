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

class SaveApprovalPolicyDraft
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Tenant $tenant, User $actor, array $data, ?ApprovalPolicy $policy = null): ApprovalPolicy
    {
        return DB::transaction(function () use ($tenant, $actor, $data, $policy): ApprovalPolicy {
            $isCreate = $policy === null;
            $policy ??= new ApprovalPolicy([
                'tenant_id' => $tenant->id,
                'created_by' => $actor->id,
                'status' => ApprovalPolicyStatus::Draft,
            ]);

            if ((int) $policy->tenant_id !== (int) $tenant->id) {
                abort(404);
            }

            $policy->fill([
                'name' => $data['name'] ?? $policy->name,
                'description' => array_key_exists('description', $data) ? $data['description'] : $policy->description,
                'subject_type' => $data['subjectType'] ?? $policy->subject_type,
                'updated_by' => $actor->id,
            ]);
            $policy->save();

            if ($this->hasVersionPayload($data)) {
                $draft = $policy->versions()
                    ->where('status', ApprovalPolicyVersionStatus::Draft->value)
                    ->first();

                if ($draft === null) {
                    $draft = new ApprovalPolicyVersion([
                        'approval_policy_id' => $policy->id,
                        'tenant_id' => $tenant->id,
                        'subject_type' => $policy->subject_type,
                        'version_number' => ((int) $policy->versions()->max('version_number')) + 1,
                        'status' => ApprovalPolicyVersionStatus::Draft,
                    ]);
                }

                $draft->fill([
                    'tenant_id' => $tenant->id,
                    'subject_type' => $policy->subject_type,
                    'priority' => (int) ($data['priority'] ?? $draft->priority ?? 100),
                    'rules' => $data['rules'] ?? $draft->rules ?? [],
                    'route_template' => $data['routeTemplate'] ?? $draft->route_template ?? ['stages' => []],
                    'sla_rules' => $data['slaRules'] ?? $draft->sla_rules ?? [],
                ]);
                $draft->save();
            }

            AuditEvent::query()->create([
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'event_type' => $isCreate ? 'approval_policy.created' : 'approval_policy.updated',
                'action' => $isCreate ? 'approval_policy.created' : 'approval_policy.updated',
                'subject_type' => ApprovalPolicy::class,
                'subject_id' => $policy->id,
                'metadata' => ['name' => $policy->name, 'subjectType' => $policy->subject_type],
                'occurred_at' => now(),
            ]);

            return $policy->load('versions');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function hasVersionPayload(array $data): bool
    {
        return array_key_exists('rules', $data)
            || array_key_exists('routeTemplate', $data)
            || array_key_exists('slaRules', $data)
            || array_key_exists('priority', $data);
    }
}
