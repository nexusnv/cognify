<?php

namespace Domains\Attachment\Policies;

use App\Models\User;
use App\Tenancy\CurrentTenant;
use Domains\Attachment\Models\Attachment;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Quotation\Models\Quotation;
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

        if ($parent instanceof Requisition) {
            return $user->can('view', $parent);
        }

        if ($parent instanceof Quotation) {
            return $parent->rfq !== null && $user->can('view', $parent->rfq);
        }

        if ($parent instanceof SupplierInvoice) {
            return $user->can('view', $parent);
        }

        return false;
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

        if ($parent instanceof Requisition) {
            return $user->can('update', $parent);
        }

        if ($parent instanceof Quotation) {
            return $parent->rfq !== null && $user->can('update', $parent->rfq);
        }

        if ($parent instanceof SupplierInvoice) {
            return $parent->purchaseOrder !== null && $user->can('captureInvoice', $parent->purchaseOrder);
        }

        return false;
    }
}
