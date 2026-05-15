<?php

namespace Domains\Project\Http\Requests;

use App\Models\User;
use App\Tenancy\CurrentTenant;
use Domains\Project\Models\ProcurementProject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateProcurementProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'charter' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'ownerId' => ['sometimes', 'required', 'integer', 'exists:users,id'],
            'budgetAmount' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'currency' => ['sometimes', 'required', 'string', 'size:3'],
            'department' => ['sometimes', 'nullable', 'string', 'max:255'],
            'costCenter' => ['sometimes', 'nullable', 'string', 'max:255'],
            'targetStartDate' => ['sometimes', 'nullable', 'date'],
            'targetCompletionDate' => ['sometimes', 'nullable', 'date', 'after_or_equal:targetStartDate'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator): void {
            $tenant = app(CurrentTenant::class)->get();
            $ownerId = $this->input('ownerId');

            if ($ownerId === null || $tenant === null) {
                return;
            }

            $belongsToTenant = User::query()
                ->whereKey($ownerId)
                ->whereHas('tenants', fn ($query) => $query->whereKey($tenant->id))
                ->exists();

            if (! $belongsToTenant) {
                $validator->errors()->add('ownerId', 'The selected owner must belong to the current tenant.');
            }
        });
    }
}
