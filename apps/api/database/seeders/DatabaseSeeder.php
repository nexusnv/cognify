<?php

namespace Database\Seeders;

use Database\Seeders\Demo\DemoAttachmentSeeder;
use Database\Seeders\Demo\DemoAuditSeeder;
use Database\Seeders\Demo\DemoNotificationSeeder;
use Database\Seeders\Demo\DemoProcurementLifecycleSeeder;
use Database\Seeders\Demo\DemoRequisitionAuthoringSeeder;
use Database\Seeders\Demo\DemoRequisitionSeeder;
use Database\Seeders\Demo\DemoRoadmapPreviewSeeder;
use Database\Seeders\Demo\DemoSeedContext;
use Database\Seeders\Demo\DemoTenantSeeder;
use Database\Seeders\Demo\DemoUserSeeder;
use Domains\Demo\Models\DemoSeedRun;
use Domains\Fulfillment\Models\FulfillmentTrackingEvent;
use Domains\Fulfillment\Models\Shipment;
use Domains\Invoice\Models\SupplierInvoice;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    private const SEEDED_AT = '2026-05-15 09:00:00';

    public function run(): void
    {
        DB::transaction(function (): void {
            $context = new DemoSeedContext;

            app(DemoTenantSeeder::class)->run($context);
            app(DemoUserSeeder::class)->run($context);
            app(DemoRequisitionSeeder::class)->run($context);
            app(DemoRequisitionAuthoringSeeder::class)->run();
            app(DemoRoadmapPreviewSeeder::class)->run($context);
            app(DemoProcurementLifecycleSeeder::class)->run($context);
            app(DemoAttachmentSeeder::class)->run($context);
            app(DemoAuditSeeder::class)->run($context);
            app(DemoNotificationSeeder::class)->run($context);

            DemoSeedRun::query()->updateOrCreate(
                ['name' => 'local-demo'],
                [
                    'seeded_at' => self::SEEDED_AT,
                    'metadata' => [
                        'tenants' => $context->tenants->count(),
                        'users' => $context->users->count(),
                        'requisitions' => $context->requisitions->count(),
                        'vendors' => $context->vendors->count(),
                        'projects' => $context->projects->count(),
                        'rfqs' => $context->rfqs->count(),
                        'quotations' => $context->quotations->count(),
                        'sourcing_intake_reviews' => $context->sourcingIntakeReviews->count(),
                        'approval_tasks' => $context->approvalTasks->count(),
                        'awards' => $context->awards->count(),
                        'quotation_normalizations' => $context->quotationNormalizations->count(),
                        'quotation_scoring_templates' => $context->quotationScoringTemplates->count(),
                        'rfq_scorecards' => $context->rfqScorecards->count(),
                        'purchase_order_request_handoffs' => $context->purchaseOrderRequestHandoffs->count(),
                        'purchase_orders' => $context->purchaseOrders->count(),
                        'shipments' => Shipment::query()->count(),
                        'fulfillment_tracking_events' => FulfillmentTrackingEvent::query()->count(),
                        'supplier_invoices' => SupplierInvoice::query()->count(),
                    ],
                ],
            );
        });
    }
}
