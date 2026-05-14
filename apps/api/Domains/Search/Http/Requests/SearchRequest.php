<?php

namespace Domains\Search\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchRequest extends FormRequest
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
            'query' => ['required', 'string', 'min:2', 'max:120'],
            'types' => ['sometimes', 'string'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:25'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            foreach ($this->typeFilters() as $type) {
                if (! in_array($type, $this->allowedTypes(), true)) {
                    $validator->errors()->add('types', 'The selected types field is invalid.');
                    return;
                }
            }
        });
    }

    /**
     * @return array<int, string>
     */
    public function typeFilters(): array
    {
        $types = trim((string) $this->query('types', ''));

        if ($types === '') {
            return ['requisition'];
        }

        $types = collect(explode(',', $types))
            ->map(fn (string $type): string => trim($type))
            ->filter()
            ->values();

        if ($types->isEmpty()) {
            return ['requisition'];
        }

        return $types->all();
    }

    /**
     * @return array<int, string>
     */
    private function allowedTypes(): array
    {
        return [
            'requisition',
            'vendor',
            'procurement_project',
            'rfq',
            'quotation',
            'award',
        ];
    }

    public function resultLimit(): int
    {
        return max(1, $this->integer('limit', 10));
    }

    public function normalizedQuery(): string
    {
        return trim((string) $this->query('query', ''));
    }
}
