<?php

namespace Domains\CreditMemo\States;

enum SupplierCreditMemoExceptionSeverity: string
{
    case Blocking = 'blocking';
    case Warning = 'warning';
    case Info = 'info';

    public function label(): string
    {
        return match ($this) {
            self::Blocking => 'Blocking',
            self::Warning => 'Warning',
            self::Info => 'Info',
        };
    }
}
