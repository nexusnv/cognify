<?php

namespace Domains\Project\Http\Requests;

use App\Tenancy\CurrentTenant;
use Domains\Requisition\Models\Requisition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class LinkProjectRequisitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'requisitionId' => ['required', 'integer', 'exists:requisitions,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
            $tenant = app(CurrentTenant::class)->get();
            $requisitionId = $this->input('requisitionId');

            if ($tenant === null || $requisitionId === null) {
                return;
            }

            $exists = Requisition::query()->where('tenant_id', $tenant->id)->whereKey($requisitionId)->exists();
            if (! $exists) {
                $validator->errors()->add('requisitionId', 'The selected requisition must belong to the current tenant.');
            }
        });
    }
}
