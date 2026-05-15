<?php

namespace Domains\Collaboration\Http\Resources;

use App\Models\User;
use Domains\Collaboration\Models\CollaborationComment;
use Domains\Requisition\Models\Requisition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;

/**
 * @mixin CollaborationComment
 */
class CollaborationCommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $mentions = $this->whenLoaded('mentions');

        return [
            'id' => (string) $this->id,
            'subjectType' => $this->subject_type === Requisition::class ? 'requisition' : 'unknown',
            'subjectId' => (string) $this->subject_id,
            'author' => $this->userSummary($this->whenLoaded('author')),
            'body' => $this->body,
            'mentions' => $mentions instanceof MissingValue ? [] : $mentions->map(function ($mention): array {
                return [
                    'id' => (string) $mention->id,
                    'mentionedUser' => $this->userSummary($mention->relationLoaded('mentionedUser') ? $mention->mentionedUser : null),
                ];
            })->all(),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * @param User|MissingValue|null $user
     * @return array{id: string, name: string, email: string|null}|null
     */
    private function userSummary(User|MissingValue|null $user): ?array
    {
        if (! $user instanceof User) {
            return null;
        }

        return [
            'id' => (string) $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }
}
