<?php

namespace Domains\AccountsPayable\Data;

class ApPaymentHandoffSnapshotData
{
    /** @param array<string, mixed> $handoff */
    public function __construct(
        public readonly array $handoff,
        public readonly array $invoices,
        public readonly array $totalByCurrency,
        public readonly array $readinessWarnings,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'handoff' => $this->handoff,
            'invoices' => $this->invoices,
            'totalByCurrency' => $this->totalByCurrency,
            'readinessWarnings' => $this->readinessWarnings,
        ];
    }
}
