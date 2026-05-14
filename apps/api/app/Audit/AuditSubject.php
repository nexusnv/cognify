<?php

namespace App\Audit;

use Domains\Attachment\Models\Attachment;
use Domains\Requisition\Models\Requisition;
use Illuminate\Database\Eloquent\Model;

class AuditSubject
{
    /**
     * @var array<class-string<Model>, string>
     */
    protected static array $typeMap = [
        Requisition::class => 'requisition',
        Attachment::class => 'attachment',
    ];

    /**
     * @param  class-string<Model>  $class
     */
    public static function registerType(string $class, string $key): void
    {
        static::$typeMap[$class] = $key;
    }

    /**
     * @param  array<class-string<Model>, string>  $mapping
     */
    public static function loadFromConfig(array $mapping): void
    {
        foreach ($mapping as $class => $key) {
            static::registerType($class, $key);
        }
    }

    public static function typeFor(Model|string $subject): string
    {
        $class = is_string($subject) ? $subject : $subject::class;

        return static::$typeMap[$class] ?? 'unknown';
    }

    /**
     * @return class-string<Model>|null
     */
    public static function classFor(string $type): ?string
    {
        $class = array_search($type, static::$typeMap, true);

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

        $filename = $subject->getAttribute('original_filename');
        if ($filename !== null) {
            return (string) $filename;
        }

        return null;
    }
}
