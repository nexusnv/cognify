<?php

namespace Domains\Quotation\Http\Requests;

use App\Tenancy\CurrentTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;

class CreateRfqScorecardRequest extends FormRequest
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
        $tenant = app(CurrentTenant::class)->get();

        return [
            'templateId' => [
                'required',
                'uuid',
                Rule::exists('quotation_scoring_templates', 'id')
                    ->where(fn (Builder $query) => $query->where('tenant_id', $tenant?->id)),
            ],
        ];
    }
}
