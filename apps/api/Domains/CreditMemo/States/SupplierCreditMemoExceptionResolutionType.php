<?php

namespace Domains\CreditMemo\States;

enum SupplierCreditMemoExceptionResolutionType: string
{
    case Accepted = 'accepted';
    case ValueAdjustment = 'value_adjustment';
    case VendorReassignment = 'vendor_reassignment';
    case Voided = 'voided';
    case InfoOnly = 'info_only';

    public function label(): string
    {
        return match ($this) {
            self::Accepted => 'Accepted',
            self::ValueAdjustment => 'Value adjustment',
            self::VendorReassignment => 'Vendor reassignment',
            self::Voided => 'Voided',
            self::InfoOnly => 'Info only',
        };
    }
}
