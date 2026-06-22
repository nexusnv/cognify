<?php

namespace Domains\Payments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ScheduleApPaymentHandoffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lockVersion' => ['required', 'integer', 'min:1'],
            'scheduledForDate' => ['nullable', 'date'],
            'paymentReference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
