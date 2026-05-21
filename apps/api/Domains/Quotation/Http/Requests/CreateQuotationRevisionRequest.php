<?php

namespace Domains\Quotation\Http\Requests;

use App\Exceptions\ApiErrorCode;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CreateQuotationRevisionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'quotationReference' => ['nullable', 'string', 'max:120'],
            'quotedAt' => ['nullable', 'date'],
            'validUntil' => ['nullable', 'date', 'after_or_equal:quotedAt'],
            'currency' => ['nullable', 'string', 'size:3'],
            'subtotalAmount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'taxAmount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'freightAmount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'discountAmount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'totalAmount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'paymentTerms' => ['nullable', 'string', 'max:255'],
            'deliveryTerms' => ['nullable', 'string', 'max:255'],
            'leadTimeDays' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'warrantyTerms' => ['nullable', 'string', 'max:5000'],
            'exclusions' => ['nullable', 'string', 'max:5000'],
            'complianceNotes' => ['nullable', 'string', 'max:5000'],
            'buyerNotes' => ['nullable', 'string', 'max:5000'],
            'vendorNotes' => ['nullable', 'string', 'max:5000'],
            'attachmentIds' => ['nullable', 'array'],
            'attachmentIds.*' => ['integer', 'distinct'],
            'lineItems' => ['present', 'array', 'max:200'],
            'lineItems.*.rfqLineItemId' => ['nullable', 'string', 'max:120'],
            'lineItems.*.description' => ['required', 'string', 'max:1000'],
            'lineItems.*.quantity' => ['required', 'numeric', 'gt:0', 'max:9999999999.9999'],
            'lineItems.*.unit' => ['nullable', 'string', 'max:80'],
            'lineItems.*.unitPrice' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'lineItems.*.subtotalAmount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'lineItems.*.taxAmount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'lineItems.*.totalAmount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'lineItems.*.leadTimeDays' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'lineItems.*.manufacturer' => ['nullable', 'string', 'max:255'],
            'lineItems.*.modelNumber' => ['nullable', 'string', 'max:255'],
            'lineItems.*.alternateOffered' => ['boolean'],
            'lineItems.*.complianceStatus' => ['nullable', Rule::in(['compliant', 'partial', 'non_compliant', 'alternate'])],
            'lineItems.*.notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        $requestId = $this->attributes->get('request_id') ?? 'req_'.Str::uuid()->toString();

        throw new HttpResponseException(
            response()
                ->json([
                    'error' => [
                        'code' => ApiErrorCode::ValidationFailed->value,
                        'message' => 'The submitted quotation revision is invalid.',
                        'details' => (object) ['fields' => $validator->errors()->toArray()],
                        'requestId' => $requestId,
                    ],
                    'errors' => $validator->errors()->toArray(),
                ], 422)
                ->withHeaders(['X-Request-Id' => $requestId])
        );
    }
}
