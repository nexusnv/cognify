<?php

namespace Tests\Feature;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Approval\Models\ApprovalTask;
use Domains\Award\Models\Award;
use Domains\Demo\Models\DemoSeedRun;
use Domains\Project\Models\ProcurementProject;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\Rfq;
use Domains\Vendor\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_models_are_tenant_scoped_and_cast_metadata(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Acme Corp',
        ]);

        $admin = User::factory()->create();
        $tenant->users()->attach($admin->id, ['role' => TenantRole::Admin->value]);

        $vendor = Vendor::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Global Freight',
            'status' => 'active',
            'category' => 'logistics',
            'risk_rating' => 'low',
            'metadata' => [
                'region' => 'APAC',
            ],
        ]);

        $project = ProcurementProject::query()->create([
            'tenant_id' => $tenant->id,
            'owner_id' => $admin->id,
            'number' => 'PRJ-1001',
            'name' => 'Office relocation',
            'status' => 'planning',
            'budget_amount' => '125000.00',
            'currency' => 'USD',
            'metadata' => [
                'program' => 'workspace-refresh',
            ],
        ]);

        $rfq = Rfq::query()->create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'requisition_id' => null,
            'number' => 'RFQ-2001',
            'title' => 'Office relocation shortlist',
            'status' => 'open',
            'due_at' => now()->addDays(7),
            'metadata' => [
                'invited_vendors' => 3,
            ],
        ]);

        $quotation = Quotation::query()->create([
            'tenant_id' => $tenant->id,
            'rfq_id' => $rfq->id,
            'vendor_id' => $vendor->id,
            'number' => 'QUO-3001',
            'status' => 'submitted',
            'total_amount' => '98500.00',
            'currency' => 'USD',
            'metadata' => [
                'lead_time_days' => 21,
            ],
        ]);

        $approvalTask = ApprovalTask::query()->create([
            'tenant_id' => $tenant->id,
            'approver_id' => $admin->id,
            'subject_type' => Quotation::class,
            'subject_id' => $quotation->id,
            'title' => 'Approve relocation quotation',
            'status' => 'pending',
            'due_at' => now()->addDays(2),
            'metadata' => [
                'stage' => 'finance',
            ],
        ]);

        $award = Award::query()->create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'rfq_id' => $rfq->id,
            'quotation_id' => $quotation->id,
            'vendor_id' => $vendor->id,
            'number' => 'AWD-4001',
            'status' => 'awarded',
            'total_amount' => '98500.00',
            'currency' => 'USD',
            'decided_at' => now(),
            'metadata' => [
                'rationale' => 'Best delivery confidence',
            ],
        ]);

        $demoSeedRun = DemoSeedRun::query()->create([
            'name' => 'local-demo',
            'seeded_at' => now(),
            'metadata' => [
                'records' => 5,
            ],
        ]);

        $this->assertSame($tenant->id, $vendor->tenant_id);
        $this->assertSame($tenant->id, $project->tenant_id);
        $this->assertSame($tenant->id, $rfq->tenant_id);
        $this->assertSame($tenant->id, $quotation->tenant_id);
        $this->assertSame($tenant->id, $approvalTask->tenant_id);
        $this->assertSame($tenant->id, $award->tenant_id);

        $this->assertSame('APAC', $vendor->metadata['region']);
        $this->assertSame(3, $rfq->metadata['invited_vendors']);
        $this->assertSame(21, $quotation->metadata['lead_time_days']);
        $this->assertSame('finance', $approvalTask->metadata['stage']);
        $this->assertSame('Best delivery confidence', $award->metadata['rationale']);
        $this->assertSame('local-demo', $demoSeedRun->name);
    }
}
