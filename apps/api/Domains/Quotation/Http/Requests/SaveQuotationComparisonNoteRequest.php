<?php

namespace Domains\Quotation\Http\Requests;

use App\Tenancy\CurrentTenant;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\Models\RfqInvitation;
use Domains\Quotation\States\QuotationComparisonNoteSection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Illuminate\Validation\Rule;

class SaveQuotationComparisonNoteRequest extends FormRequest
{
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
            'section' => ['required', 'string', Rule::in(QuotationComparisonNoteSection::values())],
            'note' => ['required', 'string', 'max:2000'],
            'quotationId' => ['nullable', 'integer'],
            'vendorId' => ['nullable', 'integer'],
            'rfqLineItemId' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(
            function (Validator $validator): void {
                $tenant = app(CurrentTenant::class)->get();
                $rfq = Rfq::query()
                    ->where('tenant_id', $tenant?->id)
                    ->find($this->route('rfq'));

                if ($tenant === null || $rfq === null) {
                    return;
                }

                $quotationId = $this->input('quotationId');
                $quotation = null;
                if ($quotationId !== null) {
                    $quotation = Quotation::query()
                        ->whereKey($quotationId)
                        ->where('tenant_id', $tenant->id)
                        ->where('rfq_id', $rfq->id)
                        ->first();

                    if ($quotation === null) {
                        $validator->errors()->add('quotationId', 'The selected quotation must belong to this RFQ.');
                    }
                }

                $vendorId = $this->input('vendorId');
                if ($vendorId !== null) {
                    $belongs = RfqInvitation::query()
                        ->where('tenant_id', $tenant->id)
                        ->where('rfq_id', $rfq->id)
                        ->where('vendor_id', $vendorId)
                        ->exists();

                    if (! $belongs) {
                        $validator->errors()->add('vendorId', 'The selected vendor must belong to this RFQ.');
                    }

                    if ($quotation !== null && (int) $quotation->vendor_id !== (int) $vendorId) {
                        $validator->errors()->add('vendorId', 'The selected vendor must match the selected quotation.');
                    }
                }

                $rfqLineItemId = $this->input('rfqLineItemId');
                if ($rfqLineItemId !== null) {
                    $lineItemIds = collect($rfq->line_items ?? [])
                        ->map(fn ($lineItem) => (string) data_get($lineItem, 'id'))
                        ->filter();

                    if (! $lineItemIds->contains((string) $rfqLineItemId)) {
                        $validator->errors()->add('rfqLineItemId', 'The selected RFQ line item must belong to this RFQ.');
                    }
                }
            },
        );
    }
}
