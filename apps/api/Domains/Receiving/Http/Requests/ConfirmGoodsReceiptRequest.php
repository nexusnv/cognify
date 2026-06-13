<?php

namespace Domains\Receiving\Http\Requests;

use Domains\Receiving\Models\GoodsReceipt;
use Illuminate\Foundation\Http\FormRequest;

class ConfirmGoodsReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        $receipt = $this->route('goodsReceipt');

        return $receipt instanceof GoodsReceipt
            && (($this->user()?->can('confirmRequester', $receipt) ?? false)
                || ($this->user()?->can('confirmBuyer', $receipt) ?? false));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lockVersion' => ['required', 'integer', 'min:1'],
        ];
    }
}
