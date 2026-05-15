<?php

namespace Domains\Collaboration\Models;

use App\Models\User;
use App\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollaborationMention extends Model
{
    protected $fillable = [
        'tenant_id',
        'comment_id',
        'mentioned_user_id',
    ];

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<CollaborationComment, $this>
     */
    public function comment(): BelongsTo
    {
        return $this->belongsTo(CollaborationComment::class, 'comment_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function mentionedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentioned_user_id');
    }
}
