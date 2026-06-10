<?php

namespace Domains\Receiving\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecordGoodsReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $purchaseOrder = $this->route('purchaseOrder');

        return $user !== null
            && $purchaseOrder !== null
            && $user->can('recordGoodsReceipt', [$purchaseOrder]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lockVersion' => ['required', 'integer', 'min:1'],
            'receiptDate' => ['required', 'date', 'before_or_equal:today'],
            'receiptReference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.purchaseOrderLineId' => ['required', 'string', 'uuid'],
            'lines.*.quantityReceived' => ['required', 'numeric', 'gt:0'],
            'lines.*.quantityAccepted' => ['nullable', 'numeric', 'gte:0'],
            'lines.*.rejectionReason' => ['nullable', 'string', 'max:1000'],
            'lines.*.notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
