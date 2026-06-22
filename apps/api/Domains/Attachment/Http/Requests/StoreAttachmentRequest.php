<?php

namespace Domains\Attachment\Http\Requests;

use Domains\Attachment\Support\AttachmentStorage;
use Illuminate\Foundation\Http\FormRequest;

class StoreAttachmentRequest extends FormRequest
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
        $extensions = implode(',', array_keys(AttachmentStorage::ALLOWED_EXTENSIONS_TO_MIME_TYPES));

        return [
            'file' => [
                'required',
                'file',
                'max:25600',
                "extensions:{$extensions}",
            ],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $file = $this->file('file');

            if (! $file) {
                return;
            }

            if ((int) $file->getSize() <= 0) {
                $validator->errors()->add('file', 'The file must not be empty.');

                return;
            }

            $storage = app(AttachmentStorage::class);
            $extension = strtolower((string) $file->getClientOriginalExtension());
            $mimeType = $file->getMimeType() ?: $file->getClientMimeType() ?: '';

            if (! $storage->isAllowedExtension($extension)) {
                $validator->errors()->add('file', 'The selected file extension is not allowed.');

                return;
            }

            if (! $storage->mimeMatchesExtension($extension, $mimeType)) {
                $validator->errors()->add('file', 'The file extension does not match its MIME type.');
            }
        });
    }
}
