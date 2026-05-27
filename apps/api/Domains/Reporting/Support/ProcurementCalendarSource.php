<?php

namespace Domains\Reporting\Support;

use App\Models\User;
use App\Tenancy\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

interface ProcurementCalendarSource
{
    public function sourceType(): string;

    /**
     * @return array{sourceType: string, label: string, available: bool, reason?: string}
     */
    public function availableSource(): array;

    /**
     * @return Collection<int, ProcurementCalendarEvent>
     */
    public function listEvents(Tenant $tenant, User $actor, CarbonImmutable $from, CarbonImmutable $to): Collection;
}
