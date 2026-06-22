<?php

namespace App\Providers;

use App\Audit\AuditEvent;
use App\Audit\AuditSubject;
use App\Audit\Policies\AuditEventPolicy;
use App\Tenancy\CurrentTenant;
use Domains\Approval\Models\ApprovalTask;
use Domains\Approval\Policies\ApprovalTaskPolicy;
use Domains\Approval\Services\ApprovalSubjectRegistry;
use Domains\Approval\SubjectHandlers\PurchaseOrderApprovalSubjectHandler;
use Domains\Approval\SubjectHandlers\RequisitionApprovalSubjectHandler;
use Domains\Approval\SubjectHandlers\RfqAwardRecommendationApprovalSubjectHandler;
use Domains\Approval\SubjectHandlers\SupplierInvoiceApprovalSubjectHandler;
use Domains\Attachment\Models\Attachment;
use Domains\Attachment\Policies\AttachmentPolicy;
use Domains\CreditMemo\Models\CreditApplication;
use Domains\CreditMemo\Models\SupplierCreditMemo;
use Domains\CreditMemo\Models\SupplierCreditMemoException;
use Domains\CreditMemo\Models\SupplierCreditMemoLine;
use Domains\CreditMemo\Policies\CreditApplicationPolicy;
use Domains\CreditMemo\Policies\SupplierCreditMemoExceptionPolicy;
use Domains\CreditMemo\Policies\SupplierCreditMemoPolicy;
use Domains\CreditMemo\SubjectHandlers\SupplierCreditMemoApprovalSubjectHandler;
use Domains\Fulfillment\Models\Shipment;
use Domains\Fulfillment\Policies\ShipmentPolicy;
use Domains\Invoice\Models\SupplierInvoice;
use Domains\Invoice\Policies\SupplierInvoicePolicy;
use Domains\Payments\Models\ApPaymentAllocation;
use Domains\Payments\Models\ApPaymentImport;
use Domains\Project\Models\ProcurementProject;
use Domains\Project\Policies\ProcurementProjectPolicy;
use Domains\PurchaseOrder\Models\PurchaseOrder;
use Domains\PurchaseOrder\Models\PurchaseOrderRequestHandoff;
use Domains\PurchaseOrder\Policies\PurchaseOrderPolicy;
use Domains\PurchaseOrder\Policies\PurchaseOrderRequestHandoffPolicy;
use Domains\Quotation\Models\QuotationComparisonNote;
use Domains\Quotation\Models\QuotationNormalization;
use Domains\Quotation\Models\Rfq;
use Domains\Quotation\Models\RfqAwardRecommendation;
use Domains\Quotation\Models\RfqInvitation;
use Domains\Quotation\Models\SourcingIntakeReview;
use Domains\Quotation\Policies\QuotationComparisonNotePolicy;
use Domains\Quotation\Policies\QuotationNormalizationPolicy;
use Domains\Quotation\Policies\RfqAwardRecommendationPolicy;
use Domains\Quotation\Policies\RfqInvitationPolicy;
use Domains\Quotation\Policies\RfqPolicy;
use Domains\Quotation\Policies\SourcingIntakeReviewPolicy;
use Domains\Receiving\Models\GoodsReceipt;
use Domains\Receiving\Policies\GoodsReceiptPolicy;
use Domains\Requisition\Models\Requisition;
use Domains\Requisition\Policies\RequisitionPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CurrentTenant::class);
        $this->app->singleton(ApprovalSubjectRegistry::class, fn ($app) => new ApprovalSubjectRegistry([
            $app->make(RequisitionApprovalSubjectHandler::class),
            $app->make(RfqAwardRecommendationApprovalSubjectHandler::class),
            $app->make(PurchaseOrderApprovalSubjectHandler::class),
            $app->make(SupplierInvoiceApprovalSubjectHandler::class),
            $app->make(SupplierCreditMemoApprovalSubjectHandler::class),
        ]));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Requisition::class, RequisitionPolicy::class);
        Gate::policy(ProcurementProject::class, ProcurementProjectPolicy::class);
        Gate::policy(SourcingIntakeReview::class, SourcingIntakeReviewPolicy::class);
        Gate::policy(QuotationComparisonNote::class, QuotationComparisonNotePolicy::class);
        Gate::policy(QuotationNormalization::class, QuotationNormalizationPolicy::class);
        Gate::policy(Rfq::class, RfqPolicy::class);
        Gate::policy(RfqAwardRecommendation::class, RfqAwardRecommendationPolicy::class);
        Gate::policy(RfqInvitation::class, RfqInvitationPolicy::class);
        Gate::policy(PurchaseOrder::class, PurchaseOrderPolicy::class);
        Gate::policy(PurchaseOrderRequestHandoff::class, PurchaseOrderRequestHandoffPolicy::class);
        Gate::policy(AuditEvent::class, AuditEventPolicy::class);
        Gate::policy(Attachment::class, AttachmentPolicy::class);
        Gate::policy(ApprovalTask::class, ApprovalTaskPolicy::class);
        Gate::policy(GoodsReceipt::class, GoodsReceiptPolicy::class);
        Gate::policy(SupplierInvoice::class, SupplierInvoicePolicy::class);
        Gate::policy(Shipment::class, ShipmentPolicy::class);
        Gate::policy(SupplierCreditMemo::class, SupplierCreditMemoPolicy::class);
        Gate::policy(CreditApplication::class, CreditApplicationPolicy::class);
        Gate::policy(SupplierCreditMemoException::class, SupplierCreditMemoExceptionPolicy::class);

        AuditSubject::registerType(ApprovalTask::class, 'approval_task');
        AuditSubject::registerType(SupplierInvoice::class, 'supplier_invoice');
        AuditSubject::registerType(ApPaymentAllocation::class, 'ap_payment_allocation');
        AuditSubject::registerType(ApPaymentImport::class, 'ap_payment_import');
        AuditSubject::registerType(SupplierCreditMemo::class, 'supplier_credit_memo');
        AuditSubject::registerType(SupplierCreditMemoLine::class, 'supplier_credit_memo_line');
        AuditSubject::registerType(CreditApplication::class, 'credit_application');
        AuditSubject::registerType(SupplierCreditMemoException::class, 'supplier_credit_memo_exception');
    }
}
