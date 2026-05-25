<?php

namespace Domains\Quotation\States;

enum RfqScorecardStatus: string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';

    public function isEditable(): bool
    {
        return $this === self::InProgress;
    }

    public function isCompleted(): bool
    {
        return $this === self::Completed;
    }
}
