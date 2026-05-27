<?php

namespace Domains\Reporting\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListProcurementCalendarEventsRequest extends FormRequest
{
    public const SOURCE_TYPES = [
        'rfqDeadline',
        'approvalDue',
        'requisitionNeededBy',
        'poHandoff',
        'quotationValidity',
        'vendorDocumentExpiry',
        'contractRenewal',
    ];

    public const STATUSES = [
        'overdue',
        'dueSoon',
        'scheduled',
        'completed',
        'informational',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'view' => ['sometimes', Rule::in(['month', 'week', 'agenda'])],
            'sourceTypes' => ['sometimes', 'array'],
            'sourceTypes.*' => ['string', Rule::in(self::SOURCE_TYPES)],
            'statuses' => ['sometimes', 'array'],
            'statuses.*' => ['string', Rule::in(self::STATUSES)],
            'q' => ['sometimes', 'nullable', 'string', 'max:120'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:500'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (! $this->filled('from') || ! $this->filled('to')) {
                return;
            }

            if ($validator->errors()->has('from') || $validator->errors()->has('to')) {
                return;
            }

            $from = CarbonImmutable::parse((string) $this->input('from'));
            $to = CarbonImmutable::parse((string) $this->input('to'));

            if ($from->diffInDays($to) > 120) {
                $validator->errors()->add('to', 'The calendar range may not exceed 120 days.');
            }
        });
    }

    /**
     * @return array{view: string, sourceTypes: array<int, string>, statuses: array<int, string>, q: string, limit: int}
     */
    public function filters(): array
    {
        return [
            'view' => (string) $this->input('view', 'month'),
            'sourceTypes' => array_values((array) $this->input('sourceTypes', [])),
            'statuses' => array_values((array) $this->input('statuses', [])),
            'q' => trim((string) $this->input('q', '')),
            'limit' => (int) $this->input('limit', 500),
        ];
    }
}
