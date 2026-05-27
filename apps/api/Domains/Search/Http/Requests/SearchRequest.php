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
            'types' => ['sometimes'],
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
        $rawTypes = $this->query('types', null);

        if (is_array($rawTypes)) {
            $types = collect($rawTypes);
        } else {
            $types = collect($this->extractRepeatedQueryTypes());

            if ($types->isEmpty() && is_string($rawTypes) && $rawTypes !== '') {
                $types = str_contains($rawTypes, ',')
                    ? collect(explode(',', $rawTypes))
                    : collect([$rawTypes]);
            }
        }

        $types = $types
            ->map(fn ($type): string => trim((string) $type))
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
    private function extractRepeatedQueryTypes(): array
    {
        $queryString = (string) $this->server('QUERY_STRING', '');

        if ($queryString === '') {
            return [];
        }

        $types = [];

        foreach (explode('&', $queryString) as $pair) {
            if ($pair === '') {
                continue;
            }

            [$rawKey, $rawValue] = array_pad(explode('=', $pair, 2), 2, '');
            $key = urldecode($rawKey);

            if ($key === 'types' || $key === 'types[]') {
                $types[] = urldecode($rawValue);
            }
        }

        return $types;
    }

    /**
     * @return array<int, string>
     */
    private function allowedTypes(): array
    {
        return [
            'requisition',
            'vendor',
            'project',
            'rfq',
            'quotation',
            'award',
            'po_handoff',
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
