<?php

namespace Tests\Unit;

use App\Tenancy\Tenant;
use Domains\Quotation\Actions\RunDeterministicQuotationNormalizer;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\QuotationNormalization;
use Domains\Quotation\Models\QuotationVersion;
use Domains\Quotation\Models\QuotationVersionLineItem;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\States\QuotationNormalizationIssueSeverity;
use Domains\Quotation\States\QuotationNormalizationStatus;
use Domains\Quotation\States\RfqStatus;
use Domains\Quotation\Support\QuotationNormalizationIssueCatalog;
use Domains\Vendor\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class QuotationDeterministicNormalizerTest extends TestCase
{
    use RefreshDatabase;

    public function test_normalizer_uppercases_currency_and_records_amount_fields(): void
    {
        [$tenant, $version, $normalization] = $this->normalizationFixture([
            'currency' => 'usd',
            'subtotal_amount' => '12000.00',
            'tax_amount' => '720.00',
            'freight_amount' => '250.00',
            'discount_amount' => '500.00',
            'total_amount' => '12470.00',
            'payment_terms' => null,
            'warranty_terms' => '3 years onsite',
        ]);

        $result = $this->normalizer()->handle($tenant, $version, $normalization);

        $this->assertSame(QuotationNormalizationStatus::ReadyForApproval, $result->refresh()->status);
        $this->assertDatabaseHas('quotation_normalization_fields', [
            'tenant_id' => $tenant->id,
            'normalization_id' => $normalization->id,
            'field_path' => 'manualEntry.currency',
            'source' => 'quotation_version',
            'currency' => 'USD',
        ]);
        $this->assertDatabaseHas('quotation_normalization_fields', [
            'tenant_id' => $tenant->id,
            'normalization_id' => $normalization->id,
            'field_path' => 'manualEntry.totalAmount',
            'source' => 'quotation_version',
        ]);
        $this->assertDatabaseHas('quotation_normalization_fields', [
            'tenant_id' => $tenant->id,
            'normalization_id' => $normalization->id,
            'field_path' => 'manualEntry.subtotalAmount',
            'source' => 'quotation_version',
        ]);
    }

    public function test_missing_currency_and_total_are_blocking_issues(): void
    {
        [$tenant, $version, $normalization] = $this->normalizationFixture([
            'currency' => null,
            'subtotal_amount' => '12000.00',
            'tax_amount' => '720.00',
            'freight_amount' => '250.00',
            'discount_amount' => '500.00',
            'total_amount' => null,
            'payment_terms' => null,
            'warranty_terms' => '3 years onsite',
        ]);

        $result = $this->normalizer()->handle($tenant, $version, $normalization);

        $this->assertSame(QuotationNormalizationStatus::NeedsReview, $result->refresh()->status);
        $this->assertDatabaseHas('quotation_normalization_issues', [
            'tenant_id' => $tenant->id,
            'normalization_id' => $normalization->id,
            'issue_code' => QuotationNormalizationIssueCatalog::MISSING_CURRENCY,
            'severity' => QuotationNormalizationIssueSeverity::Blocking->value,
        ]);
        $this->assertDatabaseHas('quotation_normalization_issues', [
            'tenant_id' => $tenant->id,
            'normalization_id' => $normalization->id,
            'issue_code' => QuotationNormalizationIssueCatalog::MISSING_TOTAL_AMOUNT,
            'severity' => QuotationNormalizationIssueSeverity::Blocking->value,
        ]);
    }

    public function test_unstructured_payment_terms_are_warning_issues(): void
    {
        [$tenant, $version, $normalization] = $this->normalizationFixture([
            'currency' => 'myr',
            'subtotal_amount' => '12000.00',
            'tax_amount' => '720.00',
            'freight_amount' => '250.00',
            'discount_amount' => '500.00',
            'total_amount' => '12470.00',
            'payment_terms' => 'Net 30 upon receipt of invoice',
            'warranty_terms' => '3 years onsite',
        ]);

        $result = $this->normalizer()->handle($tenant, $version, $normalization);

        $this->assertSame(QuotationNormalizationStatus::ReadyForApproval, $result->refresh()->status);
        $this->assertDatabaseHas('quotation_normalization_issues', [
            'tenant_id' => $tenant->id,
            'normalization_id' => $normalization->id,
            'issue_code' => QuotationNormalizationIssueCatalog::PAYMENT_TERMS_UNSTRUCTURED,
            'severity' => QuotationNormalizationIssueSeverity::Warning->value,
        ]);
    }

    public function test_attachment_snapshots_become_evidence_metadata(): void
    {
        [$tenant, $version, $normalization] = $this->normalizationFixture([
            'currency' => 'usd',
            'subtotal_amount' => '12000.00',
            'tax_amount' => '720.00',
            'freight_amount' => '250.00',
            'discount_amount' => '500.00',
            'total_amount' => '12470.00',
            'payment_terms' => null,
            'warranty_terms' => '3 years onsite',
        ], [
            [
                'id' => 'att-1',
                'filename' => 'quote.pdf',
                'mimeType' => 'application/pdf',
                'extension' => 'pdf',
                'sizeBytes' => 12345,
                'checksumSha256' => 'abc123',
                'createdAt' => '2026-05-21T00:00:00.000000Z',
                'available' => true,
            ],
        ]);

        $this->normalizer()->handle($tenant, $version, $normalization);

        $this->assertDatabaseHas('quotation_normalization_attachments', [
            'tenant_id' => $tenant->id,
            'normalization_id' => $normalization->id,
            'quotation_version_attachment_id' => 'att-1',
            'filename' => 'quote.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size_bytes' => 12345,
            'checksum_sha256' => 'abc123',
            'available' => true,
            'source' => 'quotation_version',
        ]);
    }

    public function test_total_mismatch_records_blocking_issue(): void
    {
        [$tenant, $version, $normalization] = $this->normalizationFixture([
            'currency' => 'USD',
            'subtotal_amount' => '12000.00',
            'tax_amount' => '720.00',
            'freight_amount' => '250.00',
            'discount_amount' => '500.00',
            'total_amount' => '12000.00',
            'payment_terms' => null,
            'warranty_terms' => '3 years onsite',
        ]);

        $result = $this->normalizer()->handle($tenant, $version, $normalization);

        $this->assertSame(QuotationNormalizationStatus::NeedsReview, $result->refresh()->status);
        $this->assertDatabaseHas('quotation_normalization_issues', [
            'tenant_id' => $tenant->id,
            'normalization_id' => $normalization->id,
            'issue_code' => QuotationNormalizationIssueCatalog::TOTAL_RECONCILIATION_MISMATCH,
            'severity' => QuotationNormalizationIssueSeverity::Blocking->value,
        ]);
    }

    /**
     * @param  array<string, mixed>  $versionOverrides
     * @param  array<int, array<string, mixed>>  $attachmentSnapshots
     * @return array{Tenant, QuotationVersion, QuotationNormalization}
     */
    private function normalizationFixture(array $versionOverrides = [], array $attachmentSnapshots = []): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tenant '.Str::uuid(),
        ]);

        $vendor = Vendor::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Vendor '.Str::uuid(),
            'status' => 'active',
            'category' => 'IT Hardware',
            'risk_rating' => 'low',
            'metadata' => [
                'contactName' => 'Vendor Contact',
                'contactEmail' => 'vendor@example.test',
            ],
        ]);

        $rfq = Rfq::query()->create([
            'tenant_id' => $tenant->id,
            'number' => 'RFQ-'.Str::random(8),
            'title' => 'Laptop refresh RFQ',
            'status' => RfqStatus::Draft,
            'line_items' => [
                [
                    'id' => 'rfq-line-1',
                    'name' => 'Developer laptop',
                    'description' => 'Developer laptop',
                    'quantity' => '10.0000',
                    'unit' => 'each',
                    'estimated_unit_price' => '1100.00',
                    'currency' => 'USD',
                ],
            ],
        ]);

        $quotation = Quotation::query()->create([
            'tenant_id' => $tenant->id,
            'rfq_id' => $rfq->id,
            'vendor_id' => $vendor->id,
            'number' => 'Q-'.Str::random(8),
            'status' => 'draft',
            'currency' => 'USD',
            'subtotal_amount' => $this->override($versionOverrides, 'subtotal_amount', '12000.00'),
            'tax_amount' => $this->override($versionOverrides, 'tax_amount', '720.00'),
            'freight_amount' => $this->override($versionOverrides, 'freight_amount', '250.00'),
            'discount_amount' => $this->override($versionOverrides, 'discount_amount', '500.00'),
            'total_amount' => $this->override($versionOverrides, 'total_amount', '12470.00'),
            'payment_terms' => $this->override($versionOverrides, 'payment_terms', null),
            'delivery_terms' => $this->override($versionOverrides, 'delivery_terms', 'Delivered to site'),
            'lead_time_days' => $this->override($versionOverrides, 'lead_time_days', 21),
            'warranty_terms' => $this->override($versionOverrides, 'warranty_terms', '3 years onsite'),
            'exclusions' => 'Installation not included',
            'compliance_notes' => 'Meets requested hardware specification',
            'buyer_notes' => null,
            'vendor_notes' => 'Subject to stock availability',
        ]);

        $version = QuotationVersion::query()->create([
            'tenant_id' => $tenant->id,
            'quotation_id' => $quotation->id,
            'version_number' => 1,
            'status' => 'draft',
            'currency' => $this->override($versionOverrides, 'currency', 'USD'),
            'subtotal_amount' => $this->override($versionOverrides, 'subtotal_amount', '12000.00'),
            'tax_amount' => $this->override($versionOverrides, 'tax_amount', '720.00'),
            'freight_amount' => $this->override($versionOverrides, 'freight_amount', '250.00'),
            'discount_amount' => $this->override($versionOverrides, 'discount_amount', '500.00'),
            'total_amount' => $this->override($versionOverrides, 'total_amount', '12470.00'),
            'payment_terms' => $this->override($versionOverrides, 'payment_terms', null),
            'delivery_terms' => $this->override($versionOverrides, 'delivery_terms', 'Delivered to site'),
            'lead_time_days' => $this->override($versionOverrides, 'lead_time_days', 21),
            'warranty_terms' => $this->override($versionOverrides, 'warranty_terms', '3 years onsite'),
            'exclusions' => 'Installation not included',
            'compliance_notes' => 'Meets requested hardware specification',
            'buyer_notes' => null,
            'vendor_notes' => 'Subject to stock availability',
            'attachment_snapshots' => $attachmentSnapshots,
        ]);

        QuotationVersionLineItem::query()->create([
            'tenant_id' => $tenant->id,
            'quotation_version_id' => $version->id,
            'rfq_line_item_id' => 'rfq-line-1',
            'description' => 'Developer laptop',
            'quantity' => '10.0000',
            'unit' => 'each',
            'unit_price' => '1200.00',
            'subtotal_amount' => '12000.00',
            'tax_amount' => '720.00',
            'total_amount' => '12720.00',
            'lead_time_days' => 21,
            'manufacturer' => 'Lenovo',
            'model_number' => 'ThinkPad T-series',
            'alternate_offered' => false,
            'compliance_status' => 'compliant',
            'notes' => 'Quoted as requested',
            'position' => 1,
        ]);

        $normalization = QuotationNormalization::query()->create([
            'tenant_id' => $tenant->id,
            'quotation_id' => $quotation->id,
            'quotation_version_id' => $version->id,
            'normalization_revision' => 1,
            'status' => 'pending',
            'is_current_for_version' => true,
            'algorithm_version' => 'deterministic-v1',
        ]);

        return [$tenant, $version->refresh()->load(['lineItems', 'quotation']), $normalization->refresh()];
    }

    private function normalizer(): RunDeterministicQuotationNormalizer
    {
        return app(RunDeterministicQuotationNormalizer::class);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function override(array $overrides, string $key, mixed $default): mixed
    {
        return array_key_exists($key, $overrides) ? $overrides[$key] : $default;
    }
}
