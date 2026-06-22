<?php

namespace Domains\CreditMemo\States;

enum SupplierCreditMemoStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Open = 'open';
    case PartiallyApplied = 'partially_applied';
    case FullyApplied = 'fully_applied';
    case Closed = 'closed';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingApproval => 'Pending approval',
            self::Approved => 'Approved',
            self::Open => 'Open',
            self::PartiallyApplied => 'Partially applied',
            self::FullyApplied => 'Fully applied',
            self::Closed => 'Closed',
            self::Voided => 'Voided',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Closed, self::Voided], true);
    }

    public function canAcceptCreditApplications(): bool
    {
        return in_array($this, [self::Open, self::PartiallyApplied], true);
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Draft => in_array($target, [self::PendingApproval, self::Voided], true),
            self::PendingApproval => in_array($target, [self::Approved, self::Draft, self::Voided], true),
            self::Approved => in_array($target, [self::Open, self::Voided], true),
            self::Open => in_array($target, [self::PartiallyApplied, self::FullyApplied, self::Voided], true),
            self::PartiallyApplied => in_array($target, [self::PartiallyApplied, self::FullyApplied, self::Voided], true),
            self::FullyApplied => $target === self::Closed,
            self::Closed => false,
            self::Voided => false,
        };
    }
}
