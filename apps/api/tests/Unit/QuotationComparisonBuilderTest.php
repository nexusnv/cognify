<?php

namespace Tests\Unit;

use App\Auth\TenantRole;
use App\Models\User;
use App\Tenancy\Tenant;
use Domains\Quotation\Actions\BuildQuotationComparison;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\QuotationNormalization;
use Domains\Quotation\Models\QuotationVersion;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\Models\RfqInvitation;
use Domains\Quotation\States\QuotationNormalizationStatus;
use Domains\Quotation\States\QuotationStatus;
use Domains\Quotation\States\RfqInvitationStatus;
use Domains\Quotation\States\RfqStatus;
use Domains\Vendor\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class QuotationComparisonBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_builder_uses_only_approved_normalizations_for_display_values(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Tenant '.Str::uuid()]);
        $buyer = User::factory()->create(['password' => Hash::make('secret123')]);
        $tenant->users()->attach($buyer->id, ['role' => TenantRole::Buyer->value]);
        $rfq = Rfq::query()->create([
            'tenant_id' => $tenant->id,
            'number' => 'RFQ-UNIT',
            'title' => 'Laptop refresh',
            'status' => RfqStatus::Draft->value,
            'line_items' => [[
                'id' => 'rfq-line-1',
                'name' => 'Laptop',
                'quantity' => '10',
                'unit_of_measure' => 'each',
            ]],
        ]);

        $this->quotation($tenant, $buyer, $rfq, 'Approved Vendor', 'USD', '12500.00', true);
        $this->quotation($tenant, $buyer, $rfq, 'Draft Vendor', 'USD', '9999.99', false);
        $quotation = Quotation::query()->where('rfq_id', $rfq->id)->orderBy('id')->firstOrFail();
        $rfq->comparisonNotes()->create([
            'tenant_id' => $tenant->id,
            'quotation_id' => $quotation->id,
            'vendor_id' => $quotation->vendor_id,
            'section' => 'overall',
            'note' => 'Single note with both targets.',
            'created_by_user_id' => $buyer->id,
        ]);

        $comparison = app(BuildQuotationComparison::class)->handle($tenant, $rfq);

        $this->assertSame(2, $comparison['readiness']['responseCount']);
        $this->assertSame(1, $comparison['readiness']['approvedNormalizationCount']);
        $this->assertSame(1, $comparison['readiness']['pendingNormalizationCount']);
        $this->assertSame('12500.00', $comparison['vendors'][0]['totalAmount']);
        $this->assertSame(1, $comparison['vendors'][0]['noteCount']);
        $this->assertNull($comparison['vendors'][1]['totalAmount']);
        $this->assertSame('normalization_required', $comparison['vendors'][1]['readiness']);
    }

    public function test_builder_keeps_using_latest_approved_normalization_when_new_revision_is_in_review(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Tenant '.Str::uuid()]);
        $buyer = User::factory()->create(['password' => Hash::make('secret123')]);
        $tenant->users()->attach($buyer->id, ['role' => TenantRole::Buyer->value]);
        $rfq = Rfq::query()->create([
            'tenant_id' => $tenant->id,
            'number' => 'RFQ-REVISION',
            'title' => 'Revision-safe comparison',
            'status' => RfqStatus::Draft->value,
            'line_items' => [[
                'id' => 'rfq-line-1',
                'name' => 'Laptop',
                'quantity' => '10',
                'unit_of_measure' => 'each',
            ]],
        ]);

        $this->quotation($tenant, $buyer, $rfq, 'Revision Vendor', 'USD', '12500.00', true);
        $quotation = Quotation::query()->where('rfq_id', $rfq->id)->firstOrFail();
        $version = QuotationVersion::query()->where('quotation_id', $quotation->id)->firstOrFail();
        QuotationNormalization::query()
            ->where('quotation_version_id', $version->id)
            ->where('normalization_revision', 1)
            ->update(['is_current_for_version' => false, 'superseded_at' => now()]);
        QuotationNormalization::query()->create([
            'tenant_id' => $tenant->id,
            'quotation_id' => $quotation->id,
            'quotation_version_id' => $version->id,
            'normalization_revision' => 2,
            'status' => QuotationNormalizationStatus::NeedsReview->value,
            'is_current_for_version' => true,
            'algorithm_version' => 'deterministic-v1',
        ]);

        $comparison = app(BuildQuotationComparison::class)->handle($tenant, $rfq);

        $this->assertSame(1, $comparison['readiness']['approvedNormalizationCount']);
        $this->assertSame(0, $comparison['readiness']['pendingNormalizationCount']);
        $this->assertSame('ready', $comparison['vendors'][0]['readiness']);
        $this->assertSame('12500.00', $comparison['vendors'][0]['totalAmount']);
        $this->assertSame(1, $comparison['vendors'][0]['normalizationRevision']);
    }

    private function quotation(Tenant $tenant, User $buyer, Rfq $rfq, string $vendorName, string $currency, string $total, bool $approved): void
    {
        $vendor = Vendor::query()->create([
            'tenant_id' => $tenant->id,
            'name' => $vendorName,
            'status' => 'active',
        ]);
        $invitation = RfqInvitation::query()->create([
            'tenant_id' => $tenant->id,
            'rfq_id' => $rfq->id,
            'vendor_id' => $vendor->id,
            'status' => RfqInvitationStatus::Sent->value,
            'contact_email' => Str::slug($vendorName).'@example.com',
        ]);
        $quotation = Quotation::query()->create([
            'tenant_id' => $tenant->id,
            'rfq_id' => $rfq->id,
            'rfq_invitation_id' => $invitation->id,
            'vendor_id' => $vendor->id,
            'number' => 'Q-'.Str::upper(Str::random(6)),
            'status' => QuotationStatus::submitted->value,
            'currency' => $currency,
            'total_amount' => $total,
            'manual_entry_complete' => true,
        ]);
        $version = QuotationVersion::query()->create([
            'tenant_id' => $tenant->id,
            'quotation_id' => $quotation->id,
            'version_number' => 1,
            'is_current' => true,
            'source' => 'buyer_manual_entry',
            'status' => QuotationStatus::submitted->value,
            'currency' => $currency,
            'total_amount' => $total,
            'submitted_by_user_id' => $buyer->id,
            'submitted_at' => now(),
        ]);
        $quotation->forceFill(['current_version_id' => $version->id])->save();

        $normalization = QuotationNormalization::query()->create([
            'tenant_id' => $tenant->id,
            'quotation_id' => $quotation->id,
            'quotation_version_id' => $version->id,
            'normalization_revision' => 1,
            'status' => $approved ? QuotationNormalizationStatus::Approved->value : QuotationNormalizationStatus::NeedsReview->value,
            'is_current_for_version' => true,
            'algorithm_version' => 'deterministic-v1',
        ]);
        $normalization->fields()->createMany([
            [
                'tenant_id' => $tenant->id,
                'field_path' => 'manualEntry.currency',
                'normalized_value' => $currency,
                'data_type' => 'currency',
                'source' => 'manual_entry',
            ],
            [
                'tenant_id' => $tenant->id,
                'field_path' => 'manualEntry.totalAmount',
                'normalized_value' => $total,
                'data_type' => 'money',
                'currency' => $currency,
                'source' => 'manual_entry',
            ],
        ]);
    }
}
