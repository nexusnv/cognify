<?php

namespace Domains\Attachment\Policies;

use App\Models\User;
use App\Tenancy\CurrentTenant;
use Domains\Attachment\Models\Attachment;
use Domains\Requisition\Models\Requisition;

class AttachmentPolicy
{
    public function view(User $user, Attachment $attachment): bool
    {
        $tenant = app(CurrentTenant::class)->get();

        if ($tenant === null || (int) $attachment->tenant_id !== (int) $tenant->id) {
            return false;
        }

        $parent = $attachment->attachable;

        return $parent instanceof Requisition && $user->can('view', $parent);
    }

    public function preview(User $user, Attachment $attachment): bool
    {
        return $this->view($user, $attachment);
    }

    public function download(User $user, Attachment $attachment): bool
    {
        return $this->view($user, $attachment);
    }

    public function delete(User $user, Attachment $attachment): bool
    {
        if (! $this->view($user, $attachment)) {
            return false;
        }

        $parent = $attachment->attachable;

        return $parent instanceof Requisition && $user->can('update', $parent);
    }
}
