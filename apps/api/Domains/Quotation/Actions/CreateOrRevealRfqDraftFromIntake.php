<?php

namespace Domains\Quotation\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\Models\SourcingIntakeReview;
use Domains\Quotation\States\RfqStatus;
use Domains\Quotation\States\SourcingIntakeStatus;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CreateOrRevealRfqDraftFromIntake
{
    public function __construct(private readonly AuditRecorder $audit) {}

    /**
     * @return array{rfq:Rfq, created:bool}
     */
    public function handle(Tenant $tenant, User $actor, SourcingIntakeReview $review): array
    {
        Gate::forUser($actor)->authorize('create', Rfq::class);

        if ($review->status !== SourcingIntakeStatus::ReadyForRfq) {
            throw new ConflictHttpException('Only RFQ-ready sourcing intake reviews can create draft RFQs.');
        }

        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                return DB::transaction(function () use ($tenant, $actor, $review): array {
                    $lockedReview = SourcingIntakeReview::query()
                        ->where('tenant_id', $tenant->id)
                        ->whereKey($review->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ($lockedReview->status !== SourcingIntakeStatus::ReadyForRfq) {
                        throw new ConflictHttpException('Only RFQ-ready sourcing intake reviews can create draft RFQs.');
                    }

                    $existing = Rfq::query()
                        ->where('tenant_id', $tenant->id)
                        ->where('sourcing_intake_review_id', $lockedReview->id)
                        ->where('status', RfqStatus::Draft->value)
                        ->lockForUpdate()
                        ->first();

                    if ($existing !== null) {
                        return ['rfq' => $this->loadRfq($existing), 'created' => false];
                    }

                    $lockedReview->loadMissing(['requisition.lineItems', 'project']);
                    $requisition = $lockedReview->requisition;

                    if ($requisition === null) {
                        throw new ConflictHttpException('The sourcing intake review is missing a requisition.');
                    }

                    $rfq = Rfq::query()->create([
                        'tenant_id' => $tenant->id,
                        'sourcing_intake_review_id' => $lockedReview->id,
                        'project_id' => $lockedReview->project_id,
                        'requisition_id' => $lockedReview->requisition_id,
                        'number' => $this->nextNumber($tenant),
                        'title' => $requisition->title . ' RFQ',
                        'status' => RfqStatus::Draft->value,
                        'scope_summary' => $lockedReview->decision_reason,
                        'required_documents' => [],
                        'line_items' => $requisition->lineItems->map(fn ($lineItem): array => [
                            'name' => $lineItem->name,
                            'description' => $lineItem->description,
                            'quantity' => (string) $lineItem->quantity,
                            'unit_of_measure' => $lineItem->unit_of_measure,
                            'estimated_unit_price' => (string) $lineItem->estimated_unit_price,
                            'currency' => $lineItem->currency,
                        ])->values()->all(),
                    ]);

                    $this->audit->record(new AuditEventData(
                        tenant: $tenant,
                        actor: $actor,
                        action: 'rfq.draft_created',
                        subject: $rfq,
                        metadata: ['sourcingIntakeReviewId' => (string) $lockedReview->id],
                        subjectDisplay: $rfq->number,
                    ));

                    return ['rfq' => $this->loadRfq($rfq), 'created' => true];
                });
            } catch (QueryException $exception) {
                if (! $this->isUniqueNumberViolation($exception) || $attempt === 2) {
                    throw $exception;
                }
            }
        }

        throw new ConflictHttpException('RFQ draft could not be created. Try again.');
    }

    private function nextNumber(Tenant $tenant): string
    {
        $year = now()->format('Y');
        $prefix = 'RFQ-' . $year . '-';

        $existingNumbers = Rfq::query()
            ->where('tenant_id', $tenant->id)
            ->where('number', 'like', $prefix . '%')
            ->lockForUpdate()
            ->pluck('number');

        $maxSuffix = $existingNumbers->reduce(function (int $carry, string $number) use ($prefix): int {
            if (! str_starts_with($number, $prefix)) {
                return $carry;
            }

            $suffix = substr($number, strlen($prefix));

            if ($suffix === '' || ! ctype_digit($suffix)) {
                return $carry;
            }

            return max($carry, (int) $suffix);
        }, 0);

        return $prefix . str_pad((string) ($maxSuffix + 1), 4, '0', STR_PAD_LEFT);
    }

    private function isUniqueNumberViolation(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'rfqs_tenant_id_number_unique')
            || (str_contains($message, 'unique') && str_contains($message, 'rfqs'));
    }

    private function loadRfq(Rfq $rfq): Rfq
    {
        return $rfq->refresh()->load(['sourcingIntakeReview.assignedBuyer', 'project', 'requisition.requester', 'requisition.lineItems']);
    }
}
