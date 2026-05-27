<?php

namespace Domains\Reporting\Support;

use Carbon\CarbonImmutable;

final class ProcurementCalendarEvent
{
    /**
     * @param  array<string, mixed>|null  $record
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly string $id,
        public readonly string $sourceType,
        public readonly string $sourceId,
        public readonly string $sourceLabel,
        public readonly string $title,
        public readonly ?string $description,
        public readonly CarbonImmutable $startsAt,
        public readonly ?CarbonImmutable $endsAt,
        public readonly bool $allDay,
        public readonly string $status,
        public readonly string $priority,
        public readonly ?array $record,
        public readonly array $context = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'sourceType' => $this->sourceType,
            'sourceId' => $this->sourceId,
            'sourceLabel' => $this->sourceLabel,
            'title' => $this->title,
            'description' => $this->description,
            'startsAt' => $this->startsAt->toISOString(),
            'endsAt' => $this->endsAt?->toISOString(),
            'allDay' => $this->allDay,
            'status' => $this->status,
            'priority' => $this->priority,
            'record' => $this->record,
            'context' => $this->context,
        ];
    }
}
