<?php

namespace Domains\Quotation\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\Models\QuotationScoringTemplate;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\Models\RfqScorecard;
use Domains\Quotation\States\RfqScorecardStatus;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CreateRfqScorecard
{
    public function __construct(private readonly AuditRecorder $auditRecorder) {}

    public function handle(Tenant $tenant, User $actor, Rfq $rfq, QuotationScoringTemplate $template): RfqScorecard
    {
        Gate::forUser($actor)->authorize('create', [RfqScorecard::class, $rfq]);

        try {
            return DB::transaction(function () use ($tenant, $actor, $rfq, $template): RfqScorecard {
                $lockedRfq = Rfq::query()
                    ->where('tenant_id', $tenant->id)
                    ->whereKey($rfq->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $lockedTemplate = QuotationScoringTemplate::query()
                    ->where('tenant_id', $tenant->id)
                    ->whereKey($template->id)
                    ->lockForUpdate()
                    ->firstOrFail()
                    ->load('criteria');

                if (! $lockedTemplate->is_active) {
                    throw new ConflictHttpException('Inactive scoring templates cannot be applied to an RFQ.');
                }

                if ($lockedRfq->scorecard()->exists()) {
                    throw new ConflictHttpException('An RFQ scorecard already exists for this RFQ.');
                }

                if ($lockedTemplate->criteria->isEmpty()) {
                    throw ValidationException::withMessages([
                        'templateId' => ['The selected scoring template must include at least one criterion.'],
                    ]);
                }

                $scorecard = RfqScorecard::query()->create([
                    'tenant_id' => $tenant->id,
                    'rfq_id' => $lockedRfq->id,
                    'template_id' => $lockedTemplate->id,
                    'template_name' => $lockedTemplate->name,
                    'template_description' => $lockedTemplate->description,
                    'status' => RfqScorecardStatus::InProgress->value,
                    'applied_by_user_id' => $actor->id,
                    'applied_at' => now(),
                ]);

                $scorecard->criteria()->createMany(
                    $lockedTemplate->criteria
                        ->map(fn ($criterion): array => [
                            'tenant_id' => $tenant->id,
                            'source_template_criterion_id' => $criterion->id,
                            'category' => $criterion->category?->value ?? $criterion->category,
                            'label' => $criterion->label,
                            'guidance' => $criterion->guidance,
                            'weight' => $criterion->weight,
                            'max_score' => $criterion->max_score,
                            'is_required' => $criterion->is_required,
                            'display_order' => $criterion->display_order,
                        ])
                        ->all()
                );

                $scorecard = $scorecard->refresh()->load('criteria', 'entries');

                $this->auditRecorder->record(new AuditEventData(
                    tenant: $tenant,
                    actor: $actor,
                    action: 'quotation_scorecard.created',
                    subject: $lockedRfq,
                    metadata: [
                        'scorecardId' => (string) $scorecard->id,
                        'templateId' => (string) $lockedTemplate->id,
                    ],
                    subjectDisplay: $lockedRfq->number,
                ));

                return $scorecard;
            });
        } catch (QueryException $exception) {
            if (! $this->isUniqueScorecardViolation($exception)) {
                throw $exception;
            }

            throw new ConflictHttpException('An RFQ scorecard already exists for this RFQ.', $exception);
        }
    }

    private function isUniqueScorecardViolation(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'rfq_scorecards_rfq_id_unique')
            || (str_contains($message, 'unique') && str_contains($message, 'rfq_scorecards'));
    }
}
