<?php

namespace Domains\Quotation\Policies;

use App\Models\User;
use Domains\Quotation\Models\QuotationComparisonNote;
use Domains\Quotation\Models\Rfq;

class QuotationComparisonNotePolicy
{
    public function create(User $user, Rfq $rfq): bool
    {
        return $user->can('view', $rfq);
    }

    public function update(User $user, QuotationComparisonNote $note): bool
    {
        $rfq = $note->relationLoaded('rfq')
            ? $note->rfq
            : Rfq::query()->findOrFail($note->rfq_id);

        return $user->can('view', $rfq);
    }

    public function delete(User $user, QuotationComparisonNote $note): bool
    {
        return $this->update($user, $note);
    }
}
