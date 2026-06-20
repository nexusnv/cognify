<?php

namespace Domains\CreditMemo\Support;

use Domains\CreditMemo\Models\SupplierCreditMemo;
use Domains\CreditMemo\States\SupplierCreditMemoStatus;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class SupplierCreditMemoStateMachine
{
    public function transition(SupplierCreditMemo $memo, SupplierCreditMemoStatus $target): void
    {
        $current = $memo->statusState();

        if (! $current->canTransitionTo($target)) {
            throw new ConflictHttpException(
                "Credit memo cannot transition from {$current->value} to {$target->value}.",
            );
        }

        $memo->forceFill([
            'status' => $target,
            'lock_version' => (int) $memo->lock_version + 1,
        ])->save();
    }
}
