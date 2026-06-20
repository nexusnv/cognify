<?php

namespace Domains\Payments\States;

enum ApPaymentImportTargetStatus: string
{
    case Paid = 'paid';
    case Failed = 'failed';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Paid => 'Paid',
            self::Failed => 'Failed',
            self::Voided => 'Voided',
        };
    }
}
