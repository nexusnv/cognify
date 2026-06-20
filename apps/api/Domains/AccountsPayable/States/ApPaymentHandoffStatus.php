<?php

namespace Domains\AccountsPayable\States;

enum ApPaymentHandoffStatus: string
{
    case Draft = 'draft';
    case Ready = 'ready';
    case Exported = 'exported';
    case Cancelled = 'cancelled';
    case Scheduled = 'scheduled';
    case Paid = 'paid';
    case Failed = 'failed';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Ready => 'Ready',
            self::Exported => 'Exported',
            self::Cancelled => 'Cancelled',
            self::Scheduled => 'Scheduled',
            self::Paid => 'Paid',
            self::Failed => 'Failed',
            self::Voided => 'Voided',
        };
    }

    public function isComplete(): bool
    {
        return in_array($this, [self::Exported, self::Cancelled, self::Paid, self::Failed, self::Voided], true);
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Draft => in_array($target, [self::Ready, self::Cancelled], true),
            self::Ready => in_array($target, [self::Exported, self::Cancelled], true),
            self::Exported => $target === self::Scheduled,
            self::Scheduled => in_array($target, [self::Paid, self::Failed, self::Voided], true),
            self::Paid => $target === self::Voided,
            self::Failed => in_array($target, [self::Scheduled, self::Voided], true),
            self::Voided => false,
            self::Cancelled => false,
        };
    }
}
