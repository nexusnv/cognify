<?php

namespace Domains\Approval\Models;

use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Approval\States\ApprovalPolicyStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalPolicy extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'subject_type',
        'status',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => ApprovalPolicyStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * @return HasMany<ApprovalPolicyVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(ApprovalPolicyVersion::class)->orderByDesc('version_number');
    }
}
