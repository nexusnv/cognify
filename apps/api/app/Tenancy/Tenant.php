<?php

namespace App\Tenancy;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tenant extends Model
{
    protected $fillable = [
        'slug',
        'name',
    ];

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function roleFor(User $user): ?string
    {
        $membership = $user->tenants()
            ->whereKey($this->id)
            ->first();

        return $membership?->pivot?->role;
    }
}
