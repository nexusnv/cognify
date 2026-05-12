<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

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
            'locale' => ['required', 'string', 'min:2', 'max:12'],
            'theme' => ['required', 'in:light,dark,system'],
        ];
    }
}
