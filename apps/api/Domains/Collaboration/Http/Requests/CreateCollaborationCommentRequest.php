<?php

namespace Domains\Collaboration\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateCollaborationCommentRequest extends FormRequest
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
            'body' => ['required', 'string', 'min:1', 'max:5000'],
            'mentionedUserIds' => ['sometimes', 'array', 'max:20'],
            'mentionedUserIds.*' => ['string'],
        ];
    }
}
