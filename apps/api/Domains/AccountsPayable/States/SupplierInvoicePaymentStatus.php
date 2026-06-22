<?php

namespace Domains\AccountsPayable\States;

enum SupplierInvoicePaymentStatus: string
{
    case PaymentEligible = 'payment_eligible';
    case OnHold = 'on_hold';
    case PaymentReady = 'payment_ready';
    case HandoffExported = 'handoff_exported';
    case PaymentScheduled = 'payment_scheduled';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Reversed = 'reversed';

    public function label(): string
    {
        return match ($this) {
            self::PaymentEligible => 'Payment eligible',
            self::OnHold => 'On hold',
            self::PaymentReady => 'Payment ready',
            self::HandoffExported => 'Exported',
            self::PaymentScheduled => 'Scheduled',
            self::PartiallyPaid => 'Partially paid',
            self::Paid => 'Paid',
            self::Reversed => 'Reversed',
        };
    }

    public function isEligibleForHandoff(): bool
    {
        return $this === self::PaymentEligible;
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::HandoffExported, self::Paid, self::Reversed], true);
    }

    public function canApplyCreditFrom(): bool
    {
        return in_array($this, [
            self::PaymentEligible,
            self::PaymentReady,
            self::PartiallyPaid,
            self::Paid,
        ], true);
    }
}
