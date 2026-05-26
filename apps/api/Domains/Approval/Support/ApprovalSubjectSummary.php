<?php

namespace Domains\Approval\Support;

final class ApprovalSubjectSummary
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $type,
        public readonly string $id,
        public readonly ?string $number,
        public readonly ?string $title,
        public readonly ?string $status,
        public readonly ?string $primaryParty,
        public readonly ?float $amount,
        public readonly ?string $currency,
        public readonly ?string $href,
        public readonly array $metadata = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'id' => $this->id,
            'number' => $this->number,
            'title' => $this->title,
            'status' => $this->status,
            'primaryParty' => $this->primaryParty,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'href' => $this->href,
            'metadata' => $this->metadata,
        ];
    }
}
