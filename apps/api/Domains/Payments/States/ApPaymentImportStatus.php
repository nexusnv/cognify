<?php

namespace Domains\Payments\States;

enum ApPaymentImportStatus: string
{
    case Pending = 'pending';
    case Reconciled = 'reconciled';
    case Failed = 'failed';
    case Discarded = 'discarded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Reconciled => 'Reconciled',
            self::Failed => 'Failed',
            self::Discarded => 'Discarded',
        };
    }
}
