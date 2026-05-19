<?php

namespace Domains\Quotation\Http\Requests;

use Domains\Quotation\States\RfqInvitationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRfqInvitationStatusRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([
                RfqInvitationStatus::Acknowledged->value,
                RfqInvitationStatus::Declined->value,
                RfqInvitationStatus::Expired->value,
            ])],
        ];
    }
}
