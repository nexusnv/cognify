<?php

namespace Domains\CreditMemo\Support;

use InvalidArgumentException;

class SupplierCreditMemoMathValidator
{
    public function validate(string $subtotal, string $tax, string $freight, string $total): void
    {
        $sum = bcadd(bcadd($subtotal, $tax, 4), $freight, 4);

        if (bccomp($sum, $total, 4) !== 0) {
            throw new InvalidArgumentException('Math error: subtotal + tax + freight != total');
        }
    }
}
