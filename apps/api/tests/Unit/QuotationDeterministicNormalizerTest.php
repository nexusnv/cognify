<?php

namespace Tests\Unit;

use App\Audit\AuditRecorder;
use App\Notifications\NotificationRecorder;
use App\Tenancy\Tenant;
use Domains\Quotation\Actions\CreateQuotationVersionSnapshot;
use Domains\Quotation\Actions\RunDeterministicQuotationNormalizer;
use Domains\Quotation\Actions\StartQuotationNormalization;
use Domains\Quotation\Jobs\NormalizeQuotationVersion;
use Domains\Quotation\Models\Quotation;
use Domains\Quotation\Models\QuotationLineItem;
use Domains\Quotation\Models\QuotationNormalization;
use Domains\Quotation\Models\QuotationVersion;
use Domains\Quotation\Models\QuotationVersionLineItem;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\States\QuotationNormalizationIssueSeverity;
use Domains\Quotation\States\QuotationNormalizationStatus;
use Domains\Quotation\States\QuotationSubmissionSource;
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

    public function test_existing_processing_normalization_with_attempts_is_not_re_normalized(): void
    {
        [$tenant, $version] = $this->quotableVersionFixture();

        $normalization = $this->seedProcessingNormalization($tenant, $version, 1);

        $this->mock(RunDeterministicQuotationNormalizer::class, function ($mock): void {
            $mock->shouldNotReceive('handle');
        });

        $job = new NormalizeQuotationVersion($tenant->id, $version->id);

        $job->handle(
            app(StartQuotationNormalization::class),
            app(RunDeterministicQuotationNormalizer::class),
            app(AuditRecorder::class),
            app(NotificationRecorder::class),
        );

        $this->assertSame(1, (int) $normalization->refresh()->job_attempt_count);
        $this->assertSame(1, $normalization->fields()->count());
        $this->assertSame(1, $normalization->issues()->count());
        $this->assertSame(0, $normalization->lineGroups()->count());
        $this->assertSame(0, $normalization->attachments()->count());
        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_duplicate_job_delivery_does_not_create_another_normalization_revision(): void
    {
        [$tenant, $version] = $this->quotableVersionFixture();

        $job = new NormalizeQuotationVersion($tenant->id, $version->id);

        $job->handle(
            app(StartQuotationNormalization::class),
            app(RunDeterministicQuotationNormalizer::class),
            app(AuditRecorder::class),
            app(NotificationRecorder::class),
        );
        $job->handle(
            app(StartQuotationNormalization::class),
            app(RunDeterministicQuotationNormalizer::class),
            app(AuditRecorder::class),
            app(NotificationRecorder::class),
        );

        $this->assertDatabaseCount('quotation_normalizations', 1);
        $this->assertDatabaseHas('quotation_normalizations', [
            'tenant_id' => $tenant->id,
            'quotation_version_id' => $version->id,
            'normalization_revision' => 1,
        ]);
    }

    public function test_second_delivery_after_claim_skips_without_rerunning_normalizer(): void
    {
        [$tenant, $version] = $this->quotableVersionFixture();

        $this->mock(RunDeterministicQuotationNormalizer::class, function ($mock): void {
            $mock->shouldReceive('handle')
                ->once()
                ->andReturnUsing(function (Tenant $tenant, QuotationVersion $version, QuotationNormalization $normalization): QuotationNormalization {
                    $normalization->forceFill([
                        'status' => QuotationNormalizationStatus::ReadyForApproval,
                    ])->save();

                    return $normalization->refresh();
                });
        });

        $job = new NormalizeQuotationVersion($tenant->id, $version->id);

        $job->handle(
            app(StartQuotationNormalization::class),
            app(RunDeterministicQuotationNormalizer::class),
            app(AuditRecorder::class),
            app(NotificationRecorder::class),
        );
        $job->handle(
            app(StartQuotationNormalization::class),
            app(RunDeterministicQuotationNormalizer::class),
            app(AuditRecorder::class),
            app(NotificationRecorder::class),
        );

        $normalization = QuotationNormalization::query()
            ->where('tenant_id', $tenant->id)
            ->where('quotation_version_id', $version->id)
            ->firstOrFail();

        $this->assertSame(1, (int) $normalization->job_attempt_count);
        $this->assertSame(QuotationNormalizationStatus::ReadyForApproval, $normalization->status);
        $this->assertDatabaseCount('quotation_normalizations', 1);
    }

    public function test_notification_failure_does_not_fail_successful_normalization(): void
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

        $this->mock(NotificationRecorder::class, function ($mock): void {
            $mock->shouldReceive('record')
                ->once()
                ->andThrow(new \RuntimeException('notification failed'));
        });

        $job = new NormalizeQuotationVersion($tenant->id, $version->id);

        $job->handle(
            app(StartQuotationNormalization::class),
            app(RunDeterministicQuotationNormalizer::class),
            app(AuditRecorder::class),
            app(NotificationRecorder::class),
        );

        $this->assertDatabaseHas('quotation_normalizations', [
            'id' => $normalization->id,
            'tenant_id' => $tenant->id,
            'status' => QuotationNormalizationStatus::NeedsReview->value,
        ]);
    }

    public function test_job_failure_marks_failed_and_rethrows(): void
    {
        [$tenant, $version] = $this->quotableVersionFixture();

        $this->mock(RunDeterministicQuotationNormalizer::class, function ($mock): void {
            $mock->shouldReceive('handle')
                ->once()
                ->andThrow(new \RuntimeException('normalizer exploded'));
        });

        $job = new NormalizeQuotationVersion($tenant->id, $version->id);

        try {
            $job->handle(
                app(StartQuotationNormalization::class),
                app(RunDeterministicQuotationNormalizer::class),
                app(AuditRecorder::class),
                app(NotificationRecorder::class),
            );

            $this->fail('Expected the normalization job to rethrow the failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('normalizer exploded', $exception->getMessage());
        }

        $this->assertDatabaseHas('quotation_normalizations', [
            'tenant_id' => $tenant->id,
            'quotation_version_id' => $version->id,
            'status' => QuotationNormalizationStatus::Failed->value,
            'last_job_error' => 'normalizer exploded',
        ]);
    }

    public function test_snapshot_dispatch_falls_back_to_synchronous_normalization_on_queue_failure(): void
    {
        [$tenant, $quotation] = $this->quotationSnapshotFixture();

        $service = new class(app(AuditRecorder::class)) extends CreateQuotationVersionSnapshot
        {
            public bool $fallbackCalled = false;

            protected function queueNormalizationJob(Tenant $tenant, QuotationVersion $version): void
            {
                throw new \RuntimeException('queue unavailable');
            }

            protected function runNormalizationSynchronously(Tenant $tenant, QuotationVersion $version): void
            {
                $this->fallbackCalled = true;
            }
        };

        $result = $service->handle(
            $tenant,
            $quotation,
            null,
            QuotationSubmissionSource::BuyerUpload,
        );

        $this->assertSame(1, $result->version_number);
        $this->assertTrue($service->fallbackCalled);
    }

    /**
     * @return array{Tenant, QuotationVersion}
     */
    private function quotableVersionFixture(): array
    {
        [$tenant, $version] = $this->normalizationFixture([
            'currency' => 'usd',
            'subtotal_amount' => '12000.00',
            'tax_amount' => '720.00',
            'freight_amount' => '250.00',
            'discount_amount' => '500.00',
            'total_amount' => '12470.00',
            'payment_terms' => null,
            'warranty_terms' => '3 years onsite',
        ]);

        QuotationNormalization::query()
            ->where('tenant_id', $tenant->id)
            ->where('quotation_version_id', $version->id)
            ->delete();

        return [$tenant, $version];
    }

    /**
     * @return array{Tenant, Quotation}
     */
    private function quotationSnapshotFixture(): array
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
            'subtotal_amount' => '12000.00',
            'tax_amount' => '720.00',
            'freight_amount' => '250.00',
            'discount_amount' => '500.00',
            'total_amount' => '12470.00',
            'payment_terms' => null,
            'delivery_terms' => 'Delivered to site',
            'lead_time_days' => 21,
            'warranty_terms' => '3 years onsite',
            'exclusions' => 'Installation not included',
            'compliance_notes' => 'Meets requested hardware specification',
            'buyer_notes' => null,
            'vendor_notes' => 'Subject to stock availability',
        ]);

        QuotationLineItem::query()->create([
            'tenant_id' => $tenant->id,
            'quotation_id' => $quotation->id,
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

        return [$tenant, $quotation->refresh()->load(['lineItems', 'rfq', 'vendor'])];
    }

    private function seedProcessingNormalization(Tenant $tenant, QuotationVersion $version, int $attempts): QuotationNormalization
    {
        $normalization = QuotationNormalization::query()->create([
            'tenant_id' => $tenant->id,
            'quotation_id' => $version->quotation_id,
            'quotation_version_id' => $version->id,
            'normalization_revision' => 1,
            'status' => 'processing',
            'is_current_for_version' => true,
            'job_attempt_count' => $attempts,
            'algorithm_version' => 'deterministic-v1',
        ]);

        $normalization->fields()->create([
            'tenant_id' => $tenant->id,
            'field_path' => 'manualEntry.currency',
            'raw_value' => ['value' => 'usd'],
            'normalized_value' => ['value' => 'USD'],
            'data_type' => 'text',
            'currency' => 'USD',
            'confidence' => 1.0,
            'source' => 'quotation_version',
            'provenance' => [
                'sourceQuotationVersionId' => (string) $version->id,
                'sourceFieldPath' => 'manualEntry.currency',
                'rawValue' => ['value' => 'usd'],
                'normalizedValue' => ['value' => 'USD'],
                'algorithmVersion' => 'deterministic-v1',
                'source' => 'quotation_version',
            ],
        ]);

        $normalization->issues()->create([
            'tenant_id' => $tenant->id,
            'severity' => 'warning',
            'field_path' => 'manualEntry.paymentTerms',
            'issue_code' => QuotationNormalizationIssueCatalog::PAYMENT_TERMS_UNSTRUCTURED,
            'message' => 'Payment terms are free text and require review.',
            'status' => 'open',
        ]);

        return $normalization->refresh();
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
