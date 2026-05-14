<?php

namespace Database\Seeders\Demo;

use Domains\Attachment\Models\Attachment;
use Illuminate\Support\Facades\Storage;

class DemoAttachmentSeeder
{
    public function run(DemoSeedContext $context): void
    {
        $this->seedAttachment(
            context: $context,
            tenantKey: 'acme',
            userKey: 'requester',
            requisitionKey: 'office-refresh',
            filename: 'office-refresh-brief.txt',
            contents: "Cognify local demo attachment for HQ workplace refresh.\n",
        );
        $this->seedAttachment(
            context: $context,
            tenantKey: 'northwind',
            userKey: 'vendor_manager',
            requisitionKey: 'warehouse-supplies',
            filename: 'warehouse-supplies-brief.txt',
            contents: "Cognify local demo attachment for Northwind warehouse supplies.\n",
        );
    }

    private function seedAttachment(
        DemoSeedContext $context,
        string $tenantKey,
        string $userKey,
        string $requisitionKey,
        string $filename,
        string $contents,
    ): void {
        $tenant = $context->tenants->get($tenantKey);
        $user = $context->users->get($userKey);
        $requisition = $context->requisitions->get($requisitionKey);
        $path = "tenants/{$tenant->id}/demo/{$filename}";

        Storage::disk('local')->put($path, $contents);

        $attachment = Attachment::withTrashed()->updateOrCreate(
            ['storage_disk' => 'local', 'storage_path' => $path],
            [
                'tenant_id' => $tenant->id,
                'attachable_type' => $requisition::class,
                'attachable_id' => $requisition->id,
                'uploaded_by' => $user->id,
                'original_filename' => $filename,
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
