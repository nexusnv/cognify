<?php

namespace Domains\Quotation\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\Models\QuotationScoringTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DeactivateQuotationScoringTemplate
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function handle(Tenant $tenant, User $actor, QuotationScoringTemplate $template): QuotationScoringTemplate
    {
        Gate::forUser($actor)->authorize('deactivate', $template);

        return DB::transaction(function () use ($tenant, $actor, $template): QuotationScoringTemplate {
            $lockedTemplate = QuotationScoringTemplate::query()
                ->where('tenant_id', $tenant->id)
                ->whereKey($template->id)
                ->lockForUpdate()
                ->firstOrFail();

            $before = [
                'active' => $lockedTemplate->is_active,
                'deactivatedAt' => $lockedTemplate->deactivated_at?->toISOString(),
            ];

            $lockedTemplate->forceFill([
                'is_active' => false,
                'deactivated_by_user_id' => $actor->id,
                'deactivated_at' => now(),
            ])->save();

            $lockedTemplate = $lockedTemplate->refresh()->load('criteria');

            $this->auditRecorder->record(new AuditEventData(
                tenant: $tenant,
                actor: $actor,
                action: 'quotation_scoring_template.deactivated',
                subject: $lockedTemplate,
                metadata: ['templateId' => (string) $lockedTemplate->id],
                before: $before,
                after: [
                    'active' => $lockedTemplate->is_active,
                    'deactivatedAt' => $lockedTemplate->deactivated_at?->toISOString(),
                ],
                subjectDisplay: $lockedTemplate->name,
            ));

            return $lockedTemplate;
        });
    }
}
