<?php

namespace App\Audit;

use Domains\Requisition\Models\Requisition;
use Illuminate\Database\Eloquent\Model;

class AuditSubject
{
    /**
     * @var array<class-string<Model>, string>
     */
    private const TYPE_MAP = [
        Requisition::class => 'requisition',
    ];

    public static function typeFor(Model|string $subject): string
    {
        $class = is_string($subject) ? $subject : $subject::class;

        return self::TYPE_MAP[$class] ?? 'unknown';
    }

    /**
     * @return class-string<Model>|null
     */
    public static function classFor(string $type): ?string
    {
        $class = array_search($type, self::TYPE_MAP, true);

        return $class === false ? null : $class;
    }

    public static function displayFor(Model $subject): ?string
    {
        $number = $subject->getAttribute('number');
        if ($number !== null) {
            return (string) $number;
        }

        $name = $subject->getAttribute('name');
        if ($name !== null) {
            return (string) $name;
        }

        $title = $subject->getAttribute('title');
        if ($title !== null) {
            return (string) $title;
        }

        return null;
    }
}
