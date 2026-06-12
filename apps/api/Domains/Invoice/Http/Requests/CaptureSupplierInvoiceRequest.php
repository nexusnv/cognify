<?php

namespace Domains\Invoice\Http\Requests;

use App\Exceptions\ApiErrorCode;
use Domains\PurchaseOrder\Models\PurchaseOrder;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;

class CaptureSupplierInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $purchaseOrder = $this->route('purchaseOrder');

        return $purchaseOrder instanceof PurchaseOrder
            && ($this->user()?->can('captureInvoice', $purchaseOrder) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lockVersion' => ['required', 'integer', 'min:1'],
            'invoiceNumber' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9][A-Za-z0-9\-_.\/ ]{0,99}$/'],
            'invoiceDate' => ['required', 'date'],
            'dueDate' => ['nullable', 'date', 'after_or_equal:invoiceDate'],
            'taxAmount' => ['nullable', 'regex:/^\d{1,14}(?:\.\d{1,4})?$/', 'gte:0'],
            'freightAmount' => ['nullable', 'regex:/^\d{1,14}(?:\.\d{1,4})?$/', 'gte:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.purchaseOrderLineId' => ['required', 'string', 'uuid'],
            'lines.*.quantityInvoiced' => ['required', 'regex:/^\d{1,14}(?:\.\d{1,4})?$/', 'gt:0'],
            'lines.*.unitPrice' => ['required', 'regex:/^\d{1,14}(?:\.\d{1,4})?$/', 'gte:0'],
            'lines.*.notes' => ['nullable', 'string', 'max:2000'],
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
                        'message' => 'The given data was invalid.',
                        'details' => (object) ['fields' => $validator->errors()->toArray()],
                        'requestId' => $requestId,
                    ],
                    'errors' => $validator->errors()->toArray(),
                ], 422)
                ->withHeaders(['X-Request-Id' => $requestId])
        );
    }
}
