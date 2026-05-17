<?php

namespace Domains\Project\States;

enum ProjectStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case OnHold = 'on_hold';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Draft => in_array($next, [self::Active, self::Cancelled], true),
            self::Active => in_array($next, [self::OnHold, self::Completed, self::Cancelled], true),
            self::OnHold => in_array($next, [self::Active, self::Completed, self::Cancelled], true),
            self::Completed, self::Cancelled => false,
        };
    }
}
