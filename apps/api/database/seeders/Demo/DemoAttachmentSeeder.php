<?php

namespace Database\Seeders\Demo;

use Domains\Attachment\Models\Attachment;
use Illuminate\Support\Facades\Storage;

class DemoAttachmentSeeder
{
    public function run(DemoSeedContext $context): void
    {
        $tenant = $context->tenants->get('acme');
        $user = $context->users->get('requester');
        $requisition = $context->requisitions->get('office-refresh');
        $path = "tenants/{$tenant->id}/demo/office-refresh-brief.txt";
        $contents = "Cognify local demo attachment for HQ workplace refresh.\n";

        Storage::disk('local')->put($path, $contents);

        $attachment = Attachment::withTrashed()->updateOrCreate(
            ['storage_disk' => 'local', 'storage_path' => $path],
            [
                'tenant_id' => $tenant->id,
                'attachable_type' => $requisition::class,
                'attachable_id' => $requisition->id,
                'uploaded_by' => $user->id,
                'original_filename' => 'office-refresh-brief.txt',
                'mime_type' => 'text/plain',
                'extension' => 'txt',
                'size_bytes' => strlen($contents),
                'storage_disk' => 'local',
                'checksum_sha256' => hash('sha256', $contents),
                'previewable' => true,
            ],
        );

        if ($attachment->trashed()) {
            $attachment->restore();
        }
    }
}
