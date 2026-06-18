<?php

namespace Domains\AccountsPayable\States;

enum SupplierInvoicePaymentStatus: string
{
    case PaymentEligible = 'payment_eligible';
    case OnHold = 'on_hold';
    case PaymentReady = 'payment_ready';
    case HandoffExported = 'handoff_exported';

    public function label(): string
    {
        return match ($this) {
            self::PaymentEligible => 'Payment eligible',
            self::OnHold => 'On hold',
            self::PaymentReady => 'Payment ready',
            self::HandoffExported => 'Exported',
        };
    }

    public function isEligibleForHandoff(): bool
    {
        return $this === self::PaymentEligible;
    }

    public function isTerminal(): bool
    {
        return $this === self::HandoffExported;
    }
}
