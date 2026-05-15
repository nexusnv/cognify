<?php

namespace Domains\Project\Http\Requests;

use App\Models\User;
use App\Tenancy\CurrentTenant;
use Domains\Project\Models\ProcurementProject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreProcurementProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ProcurementProject::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'charter' => ['nullable', 'string', 'max:5000'],
            'ownerId' => ['required', 'integer', 'exists:users,id'],
            'budgetAmount' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'currency' => ['required', 'string', 'size:3'],
            'department' => ['nullable', 'string', 'max:255'],
            'costCenter' => ['nullable', 'string', 'max:255'],
            'targetStartDate' => ['nullable', 'date'],
            'targetCompletionDate' => ['nullable', 'date', 'after_or_equal:targetStartDate'],
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
