<?php

namespace Domains\Collaboration\Actions;

use App\Audit\AuditEventData;
use App\Audit\AuditRecorder;
use App\Models\User;
use App\Notifications\NotificationData;
use App\Notifications\NotificationPreferenceDefaults;
use App\Notifications\NotificationRecorder;
use App\Tenancy\Tenant;
use Domains\Collaboration\Models\CollaborationComment;
use Domains\Requisition\Models\Requisition;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class CreateCollaborationComment
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
        private readonly NotificationRecorder $notificationRecorder,
    ) {}

    /**
     * @param  array{body:string,mentionedUserIds?:array<int,string>}  $data
     */
    public function handle(Tenant $tenant, User $actor, Requisition $requisition, array $data): CollaborationComment
    {
        if (! $actor->can('comment', $requisition)) {
            throw new AccessDeniedHttpException('You are not allowed to perform this action.');
        }

        $mentionedUsers = $this->visibleMentionedUsers(
            tenant: $tenant,
            actor: $actor,
            requisition: $requisition,
            mentionedUserIds: $data['mentionedUserIds'] ?? [],
        );

        return DB::transaction(function () use ($tenant, $actor, $requisition, $data, $mentionedUsers): CollaborationComment {
            $comment = CollaborationComment::query()->create([
                'tenant_id' => $tenant->id,
                'subject_type' => Requisition::class,
                'subject_id' => $requisition->id,
                'author_id' => $actor->id,
                'body' => $data['body'],
            ]);

            $comment->mentions()->createMany(
                $mentionedUsers->map(fn (User $user): array => [
                    'tenant_id' => $tenant->id,
                    'mentioned_user_id' => $user->id,
                ])->all(),
            );

            $mentionIds = $mentionedUsers->pluck('id')->map(fn (int $id): string => (string) $id)->all();

            $this->auditRecorder->record(new AuditEventData(
                tenant: $tenant,
                actor: $actor,
                action: 'collaboration.comment_created',
                subject: $requisition,
                metadata: [
                    'subjectType' => 'requisition',
                    'subjectId' => (string) $requisition->id,
                    'mentionCount' => count($mentionIds),
                    'mentionedUserIds' => $mentionIds,
                ],
                after: [
                    'body' => $comment->body,
                    'mentionCount' => count($mentionIds),
                ],
                subjectDisplay: $requisition->number,
            ));

            if ($mentionedUsers->isNotEmpty()) {
                $this->auditRecorder->record(new AuditEventData(
                    tenant: $tenant,
                    actor: $actor,
                    action: 'collaboration.mentioned',
                    subject: $requisition,
                    metadata: [
                        'subjectType' => 'requisition',
                        'subjectId' => (string) $requisition->id,
                        'mentionedUserIds' => $mentionIds,
                    ],
                    subjectDisplay: $requisition->number,
                ));

                $this->notificationRecorder->record(
                    tenant: $tenant,
                    recipients: $mentionedUsers->reject(fn (User $recipient): bool => $recipient->id === $actor->id),
                    data: new NotificationData(
                        type: NotificationPreferenceDefaults::EVENT_COLLABORATION_MENTIONED,
                        title: 'You were mentioned',
                        body: "{$actor->name} mentioned you on {$requisition->number}.",
                        href: "/requisitions/{$requisition->id}",
                        subject: $requisition,
                        subjectLabel: $requisition->number,
                        metadata: [
                            'commentId' => (string) $comment->id,
                            'number' => $requisition->number,
                        ],
                        actor: $actor,
                    ),
                );
            }

            return $comment->refresh()->load(['author', 'mentions.mentionedUser']);
        });
    }

    /**
     * @param  array<int, string>  $mentionedUserIds
     * @return Collection<int, User>
     */
    private function visibleMentionedUsers(Tenant $tenant, User $actor, Requisition $requisition, array $mentionedUserIds): Collection
    {
        $selected = collect($mentionedUserIds)
            ->filter(fn (string $userId): bool => $userId !== '')
            ->unique()
            ->values();

        $visibleUsers = $tenant->users()
            ->whereIn('users.id', $selected)
            ->get()
            ->filter(fn (User $user): bool => Gate::forUser($user)->allows('view', $requisition))
            ->values();

        $invalid = $selected->diff($visibleUsers->pluck('id')->map(fn (int $id): string => (string) $id));

        if ($invalid->isNotEmpty()) {
            throw ValidationException::withMessages([
                'mentionedUserIds' => 'The selected mentioned user ids are invalid.',
            ]);
        }

        return $visibleUsers->filter(
            fn (User $user): bool => $selected->contains((string) $user->id),
        )->values();
    }
}
