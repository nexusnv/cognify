<?php

namespace Domains\Attachment\Support;

use App\Tenancy\Tenant;
use Domains\Attachment\Models\Attachment;
use Domains\Requisition\Models\Requisition;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class AttachmentStorage
{
    public const DISK = 'attachments';

    /**
     * @var array<int, string>
     */
    public const PREVIEWABLE_MIME_TYPES = [
        'application/pdf',
        'image/png',
        'image/jpeg',
        'image/webp',
    ];

    /**
     * @var array<string, array<int, string>>
     */
    public const ALLOWED_EXTENSIONS_TO_MIME_TYPES = [
        'pdf' => ['application/pdf'],
        'png' => ['image/png'],
        'jpeg' => ['image/jpeg'],
        'jpg' => ['image/jpeg'],
        'webp' => ['image/webp'],
        'csv' => ['text/csv'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'txt' => ['text/plain'],
    ];

    /**
     * @return array{
     *     disk: string,
     *     path: string,
     *     checksum: string,
     *     previewable: bool,
     *     sizeBytes: int,
     *     mimeType: string,
     *     extension: string|null,
     *     originalFilename: string
     * }
     */
    public function store(Tenant $tenant, Requisition $requisition, UploadedFile $file): array
    {
        $extension = $this->extensionFor($file);
        $safeFilename = $this->safeFilename($file);
        $directory = sprintf(
            'tenants/%s/requisitions/%s/%s',
            $tenant->id,
            $requisition->id,
            Str::uuid()->toString(),
        );
        $filename = $extension !== null ? "{$safeFilename}.{$extension}" : $safeFilename;
        $path = "{$directory}/{$filename}";
        $realPath = $file->getRealPath();
        $checksum = $realPath !== false ? hash_file('sha256', $realPath) : false;

        if ($checksum === false) {
            throw new RuntimeException('Unable to calculate attachment checksum.');
        }

        Storage::disk(self::DISK)->putFileAs($directory, $file, $filename);

        return [
            'disk' => self::DISK,
            'path' => $path,
            'checksum' => $checksum,
            'previewable' => in_array($this->mimeTypeFor($file), self::PREVIEWABLE_MIME_TYPES, true),
            'sizeBytes' => (int) $file->getSize(),
            'mimeType' => $this->mimeTypeFor($file),
            'extension' => $extension,
            'originalFilename' => $file->getClientOriginalName(),
        ];
    }

    public function exists(Attachment $attachment): bool
    {
        return Storage::disk($attachment->storage_disk)->exists($attachment->storage_path);
    }

    public function previewableMimeTypes(): array
    {
        return self::PREVIEWABLE_MIME_TYPES;
    }

    public function allowedExtensions(): array
    {
        return array_keys(self::ALLOWED_EXTENSIONS_TO_MIME_TYPES);
    }

    public function isAllowedExtension(string $extension): bool
    {
        return array_key_exists(strtolower($extension), self::ALLOWED_EXTENSIONS_TO_MIME_TYPES);
    }

    public function mimeMatchesExtension(string $extension, string $mimeType): bool
    {
        $extension = strtolower($extension);

        return in_array($mimeType, self::ALLOWED_EXTENSIONS_TO_MIME_TYPES[$extension] ?? [], true);
    }

    private function safeFilename(UploadedFile $file): string
    {
        $base = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $slug = Str::slug($base);

        return $slug !== '' ? $slug : 'attachment';
    }

    private function extensionFor(UploadedFile $file): ?string
    {
        $extension = strtolower((string) $file->getClientOriginalExtension());

        if ($extension === '') {
            $guessed = strtolower((string) $file->extension());
            $extension = $guessed !== '' ? $guessed : '';
        }

        return $extension !== '' ? $extension : null;
    }

    private function mimeTypeFor(UploadedFile $file): string
    {
        return $file->getMimeType()
            ?: $file->getClientMimeType()
            ?: 'application/octet-stream';
    }
}
