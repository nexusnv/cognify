<?php

namespace Domains\Receiving\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmGoodsReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
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
