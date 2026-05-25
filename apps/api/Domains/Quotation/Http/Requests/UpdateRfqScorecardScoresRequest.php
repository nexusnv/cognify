<?php

namespace Domains\Quotation\Http\Requests;

use App\Tenancy\CurrentTenant;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\Models\RfqScorecardCriterion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class UpdateRfqScorecardScoresRequest extends FormRequest
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
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.criterionId' => ['required', 'uuid'],
            'entries.*.vendorId' => ['required', 'integer'],
            'entries.*.quotationId' => ['nullable', 'integer'],
            'entries.*.quotationVersionId' => ['nullable', 'integer'],
            'entries.*.score' => ['nullable', 'numeric', 'min:0'],
            'entries.*.note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $tenant = app(CurrentTenant::class)->get();
            $rfqId = $this->route('rfq');
            $entries = $this->input('entries');

            if ($tenant === null || ! is_array($entries) || $entries === []) {
                return;
            }

            $rfq = Rfq::query()
                ->where('tenant_id', $tenant->id)
                ->with('scorecard')
                ->find($rfqId);

            if ($rfq === null || $rfq->scorecard === null) {
                return;
            }

            $criterionIds = collect($entries)
                ->pluck('criterionId')
                ->filter(static fn (mixed $value): bool => is_string($value) && $value !== '')
                ->unique()
                ->values();

            if ($criterionIds->isEmpty()) {
                return;
            }

            $criteria = RfqScorecardCriterion::query()
                ->where('tenant_id', $tenant->id)
                ->where('scorecard_id', $rfq->scorecard->id)
                ->whereIn('id', $criterionIds->all())
                ->get()
                ->keyBy(fn (RfqScorecardCriterion $criterion): string => (string) $criterion->id);
            $scoreErrors = [];

            foreach (array_values($entries) as $index => $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                $score = $entry['score'] ?? null;
                $criterionId = $entry['criterionId'] ?? null;

                if ($score === null || ! is_numeric($score) || ! is_string($criterionId)) {
                    continue;
                }

                /** @var RfqScorecardCriterion|null $criterion */
                $criterion = $criteria->get($criterionId);

                if ($criterion !== null && (float) $score > (float) $criterion->max_score) {
                    $scoreErrors[$index]['score'][] = sprintf(
                        'The score may not be greater than %d.',
                        $criterion->max_score,
                    );
                }
            }

            if ($scoreErrors !== []) {
                throw ValidationException::withMessages([
                    'entries' => $scoreErrors,
                ]);
            }
        });
    }
}
