<?php

namespace Domains\Quotation\Http\Requests;

use Domains\Quotation\States\QuotationScoringCriterionCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveQuotationScoringTemplateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $criteria = $this->input('criteria');

        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }

        if (is_string($this->input('description'))) {
            $this->merge(['description' => trim($this->input('description'))]);
        }

        if (is_array($criteria)) {
            $this->merge([
                'criteria' => array_map(static function (mixed $criterion): mixed {
                    if (! is_array($criterion)) {
                        return $criterion;
                    }

                    if (array_key_exists('label', $criterion) && is_string($criterion['label'])) {
                        $criterion['label'] = trim($criterion['label']);
                    }

                    if (array_key_exists('guidance', $criterion) && is_string($criterion['guidance'])) {
                        $criterion['guidance'] = trim($criterion['guidance']);
                    }

                    return $criterion;
                }, $criteria),
            ]);
        }
    }

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
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'criteria' => ['required', 'array', 'min:1'],
            'criteria.*.category' => ['required', Rule::enum(QuotationScoringCriterionCategory::class)],
            'criteria.*.label' => ['required', 'string', 'max:160'],
            'criteria.*.guidance' => ['nullable', 'string', 'max:2000'],
            'criteria.*.weight' => ['required', 'numeric', 'gt:0'],
            'criteria.*.maxScore' => ['required', 'integer', 'min:1', 'max:100'],
            'criteria.*.required' => ['required', 'boolean'],
            'criteria.*.displayOrder' => ['required', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $criteria = $this->input('criteria');

            if (! is_array($criteria)) {
                return;
            }

            $displayOrders = collect($criteria)
                ->pluck('displayOrder')
                ->filter(static fn (mixed $value): bool => $value !== null && $value !== '');

            if ($displayOrders->count() !== $displayOrders->unique()->count()) {
                $validator->errors()->add('criteria', 'Criterion display orders must be unique.');
            }
        });
    }
}
