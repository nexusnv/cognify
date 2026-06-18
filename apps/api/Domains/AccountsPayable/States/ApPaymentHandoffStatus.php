<?php

namespace Domains\AccountsPayable\States;

enum ApPaymentHandoffStatus: string
{
    case Draft = 'draft';
    case Ready = 'ready';
    case Exported = 'exported';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Ready => 'Ready',
            self::Exported => 'Exported',
            self::Cancelled => 'Cancelled',
        };
    }

    public function isTerminal(): bool
    {
        return $this === self::Exported || $this === self::Cancelled;
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Draft => in_array($target, [self::Ready, self::Cancelled], true),
            self::Ready => in_array($target, [self::Exported, self::Cancelled], true),
            self::Exported => $target === self::Exported,
            self::Cancelled => false,
        };
    }
}
