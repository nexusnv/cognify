<?php

namespace App\Http\Requests\Auth;

use App\Notifications\NotificationPreferenceDefaults;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'avatarUrl' => ['nullable', 'url', 'max:2048'],
            'timezone' => ['required', 'timezone', 'max:64'],
            'locale' => ['required', 'string', Rule::in(config('app.supported_locales', ['en']))],
            'theme' => ['required', 'in:light,dark,system'],
            ...NotificationPreferenceDefaults::validationRules(),
        ];
    }
}
