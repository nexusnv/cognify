<?php

namespace Domains\Demo\Models;

use Illuminate\Database\Eloquent\Model;

class DemoSeedRun extends Model
{
    protected $fillable = [
        'name',
        'seeded_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'seeded_at' => 'datetime',
            'metadata' => 'array',
        ];
    }
}
