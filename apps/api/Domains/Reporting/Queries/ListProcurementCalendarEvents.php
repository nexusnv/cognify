<?php

namespace Domains\Reporting\Queries;

use App\Models\User;
use App\Tenancy\Tenant;
use Carbon\CarbonImmutable;
use Domains\Reporting\Support\ApprovalDueCalendarSource;
use Domains\Reporting\Support\ProcurementCalendarEvent;
use Domains\Reporting\Support\ProcurementCalendarSource;
use Domains\Reporting\Support\PurchaseOrderHandoffCalendarSource;
use Domains\Reporting\Support\QuotationValidityCalendarSource;
use Domains\Reporting\Support\RequisitionNeededByCalendarSource;
use Domains\Reporting\Support\RfqDeadlineCalendarSource;
use Illuminate\Support\Collection;

final class ListProcurementCalendarEvents
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function handle(
        Tenant $tenant,
        User $actor,
        CarbonImmutable $from,
        CarbonImmutable $to,
        array $filters,
    ): array {
        $selectedSourceTypes = collect($filters['sourceTypes'] ?? [])->filter()->values();
        $selectedStatuses = collect($filters['statuses'] ?? [])->filter()->values();
        $search = mb_strtolower(trim((string) ($filters['q'] ?? '')));
        $limit = max(1, (int) ($filters['limit'] ?? 500));

        $allSources = collect($this->sources());
        $queriedSources = $allSources->filter(function (ProcurementCalendarSource $source) use ($selectedSourceTypes): bool {
            return $selectedSourceTypes->isEmpty() || $selectedSourceTypes->contains($source->sourceType());
        });

        $events = $queriedSources
            ->flatMap(fn (ProcurementCalendarSource $source): Collection => $source->listEvents($tenant, $actor, $from, $to))
            ->values();

        if ($selectedStatuses->isNotEmpty()) {
            $events = $events
                ->filter(fn (ProcurementCalendarEvent $event): bool => $selectedStatuses->contains($event->status))
                ->values();
        }

        if ($search !== '') {
            $events = $events
                ->filter(fn (ProcurementCalendarEvent $event): bool => $this->matchesSearch($event, $search))
                ->values();
        }

        $sortedEvents = $events
            ->sortBy([
                fn (ProcurementCalendarEvent $event): int => $event->startsAt->getTimestamp(),
                fn (ProcurementCalendarEvent $event): int => $this->priorityRank($event->priority),
                fn (ProcurementCalendarEvent $event): string => mb_strtolower($event->title),
            ])
            ->values();

        $summary = [
            'total' => array_sum($this->statusSummary($sortedEvents)),
            'byStatus' => $this->statusSummary($sortedEvents),
            'bySourceType' => $this->sourceSummary($sortedEvents),
        ];

        return [
            'range' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'timezone' => config('app.timezone'),
            ],
            'summary' => $summary,
            'availableSources' => $this->availableSources($allSources),
            'events' => $sortedEvents->take($limit)->map(
                fn (ProcurementCalendarEvent $event): array => $event->toArray()
            )->values()->all(),
        ];
    }

    /**
     * @return array<int, ProcurementCalendarSource>
     */
    private function sources(): array
    {
        return [
            app(RfqDeadlineCalendarSource::class),
            app(ApprovalDueCalendarSource::class),
            app(RequisitionNeededByCalendarSource::class),
            app(PurchaseOrderHandoffCalendarSource::class),
            app(QuotationValidityCalendarSource::class),
        ];
    }

    /**
     * @param  Collection<int, ProcurementCalendarSource>  $sources
     * @return array<int, array<string, mixed>>
     */
    private function availableSources(Collection $sources): array
    {
        return [
            ...$sources->map(fn (ProcurementCalendarSource $source): array => $source->availableSource())->all(),
            [
                'sourceType' => 'vendorDocumentExpiry',
                'label' => 'Vendor document expiry',
                'available' => false,
                'reason' => 'Vendor document expiry dates are not captured yet.',
            ],
            [
                'sourceType' => 'contractRenewal',
                'label' => 'Contract renewal',
                'available' => false,
                'reason' => 'Contract renewal dates are not captured yet.',
            ],
        ];
    }

    /**
     * @param  Collection<int, ProcurementCalendarEvent>  $events
     * @return array<string, int>
     */
    private function statusSummary(Collection $events): array
    {
        $summary = collect([
            'overdue' => 0,
            'dueSoon' => 0,
            'scheduled' => 0,
            'completed' => 0,
            'informational' => 0,
        ]);

        foreach ($events as $event) {
            $summary->put($event->status, (int) $summary->get($event->status, 0) + 1);
        }

        return $summary->all();
    }

    /**
     * @param  Collection<int, ProcurementCalendarEvent>  $events
     * @return array<string, int>
     */
    private function sourceSummary(Collection $events): array
    {
        $summary = collect([
            'rfqDeadline' => 0,
            'approvalDue' => 0,
            'requisitionNeededBy' => 0,
            'poHandoff' => 0,
            'quotationValidity' => 0,
            'vendorDocumentExpiry' => 0,
            'contractRenewal' => 0,
        ]);

        foreach ($events as $event) {
            $summary->put($event->sourceType, (int) $summary->get($event->sourceType, 0) + 1);
        }

        return $summary->all();
    }

    private function matchesSearch(ProcurementCalendarEvent $event, string $search): bool
    {
        $haystacks = [
            $event->title,
            $event->description,
            $event->sourceLabel,
            json_encode($event->context),
        ];

        foreach ($haystacks as $haystack) {
            if ($haystack !== null && str_contains(mb_strtolower((string) $haystack), $search)) {
                return true;
            }
        }

        return false;
    }

    private function priorityRank(string $priority): int
    {
        return match ($priority) {
            'critical' => 0,
            'high' => 1,
            'normal' => 2,
            'low' => 3,
            default => 4,
        };
    }
}
