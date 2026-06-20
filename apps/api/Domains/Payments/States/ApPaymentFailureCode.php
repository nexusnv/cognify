<?php

namespace Domains\Payments\States;

enum ApPaymentFailureCode: string
{
    case BankRejected = 'bank_rejected';
    case InsufficientFunds = 'insufficient_funds';
    case VendorBlocked = 'vendor_blocked';
    case SystemError = 'system_error';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::BankRejected => 'Bank rejected',
            self::InsufficientFunds => 'Insufficient funds',
            self::VendorBlocked => 'Vendor blocked',
            self::SystemError => 'System error',
            self::Other => 'Other',
        };
    }
}
