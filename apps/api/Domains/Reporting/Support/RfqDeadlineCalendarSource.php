<?php

namespace Domains\Reporting\Support;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\CurrentTenant;
use App\Tenancy\Tenant;
use Carbon\CarbonImmutable;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\Models\RfqInvitation;
use Illuminate\Support\Collection;

final class RfqDeadlineCalendarSource implements ProcurementCalendarSource
{
    public function __construct(private readonly CurrentTenant $currentTenant) {}

    public function sourceType(): string
    {
        return 'rfqDeadline';
    }

    public function availableSource(): array
    {
        return [
            'sourceType' => $this->sourceType(),
            'label' => 'RFQ deadlines',
            'available' => true,
        ];
    }

    public function listEvents(Tenant $tenant, User $actor, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        if (! $this->canView($actor)) {
            return collect();
        }

        $rfqEvents = Rfq::query()
            ->select(['id', 'tenant_id', 'number', 'title', 'response_due_at'])
            ->where('tenant_id', $tenant->id)
            ->whereNotNull('response_due_at')
            ->whereBetween('response_due_at', [$from, $to])
            ->get()
            ->map(function (Rfq $rfq): ProcurementCalendarEvent {
                $dueAt = CarbonImmutable::instance($rfq->response_due_at);

                return new ProcurementCalendarEvent(
                    id: sprintf('rfq:%s:response_due', (string) $rfq->id),
                    sourceType: $this->sourceType(),
                    sourceId: (string) $rfq->id,
                    sourceLabel: 'RFQ deadlines',
                    title: (string) ($rfq->title ?: $rfq->number ?: 'RFQ deadline'),
                    description: $rfq->number ? sprintf('RFQ %s response deadline', $rfq->number) : null,
                    startsAt: $dueAt,
                    endsAt: null,
                    allDay: false,
                    status: $this->statusFor($dueAt),
                    priority: 'high',
                    record: [
                        'type' => 'rfq',
                        'id' => (string) $rfq->id,
                        'label' => (string) ($rfq->title ?: $rfq->number ?: 'RFQ'),
                        'href' => sprintf('/sourcing/rfqs/%s', (string) $rfq->id),
                    ],
                    context: array_filter([
                        'number' => $rfq->number,
                    ], static fn (mixed $value): bool => $value !== null && $value !== ''),
                );
            });

        $invitationEvents = RfqInvitation::query()
            ->select(['id', 'tenant_id', 'rfq_id', 'response_due_at'])
            ->with(['rfq:id,title,number,response_due_at'])
            ->where('tenant_id', $tenant->id)
            ->whereNotNull('response_due_at')
            ->whereBetween('response_due_at', [$from, $to])
            ->get()
            ->filter(function (RfqInvitation $invitation): bool {
                if ($invitation->rfq === null || $invitation->response_due_at === null) {
                    return false;
                }

                $rfqDueAt = $invitation->rfq->response_due_at;

                if ($rfqDueAt === null) {
                    return true;
                }

                return ! $invitation->response_due_at->equalTo($rfqDueAt);
            })
            ->map(function (RfqInvitation $invitation): ProcurementCalendarEvent {
                $dueAt = CarbonImmutable::instance($invitation->response_due_at);

                return new ProcurementCalendarEvent(
                    id: sprintf('rfq-invitation:%s:response_due', (string) $invitation->id),
                    sourceType: $this->sourceType(),
                    sourceId: (string) $invitation->id,
                    sourceLabel: 'RFQ deadlines',
                    title: 'Vendor invitation deadline',
                    description: 'Invitation-specific response deadline',
                    startsAt: $dueAt,
                    endsAt: null,
                    allDay: false,
                    status: $this->statusFor($dueAt),
                    priority: 'high',
                    record: [
                        'type' => 'rfq',
                        'id' => (string) $invitation->rfq_id,
                        'label' => (string) ($invitation->rfq?->title ?: $invitation->rfq?->number ?: 'RFQ'),
                        'href' => sprintf('/sourcing/rfqs/%s', (string) $invitation->rfq_id),
                    ],
                    context: [
                        'rfqId' => (string) $invitation->rfq_id,
                    ],
                );
            });

        return $rfqEvents->concat($invitationEvents)->values();
    }

    private function canView(User $actor): bool
    {
        return in_array($this->currentTenant->roleFor($actor), [
            TenantRole::Buyer->value,
            TenantRole::Admin->value,
        ], true);
    }

    private function statusFor(CarbonImmutable $date): string
    {
        if ($date->isPast()) {
            return 'overdue';
        }

        if ($date->lessThanOrEqualTo(CarbonImmutable::now()->addDays(7))) {
            return 'dueSoon';
        }

        return 'scheduled';
    }
}
