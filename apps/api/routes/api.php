<?php

use App\Audit\Http\Controllers\AuditEventController;
use App\Auth\Http\Controllers\AuthenticatedSessionController;
use App\Auth\Http\Controllers\CurrentTenantController;
use App\Auth\Http\Controllers\CurrentUserController;
use App\Auth\Http\Controllers\UserProfileController;
use App\Http\Middleware\RequireTenantHeader;
use App\Http\Middleware\ResolveCurrentTenant;
use App\Notifications\Http\Controllers\NotificationController;
use App\Observability\SystemStatus\Http\Controllers\SystemStatusController;
use Domains\Approval\Http\Controllers\ApprovalDelegationController;
use Domains\Approval\Http\Controllers\ApprovalPolicyController;
use Domains\Approval\Http\Controllers\ApprovalPolicyVersionController;
use Domains\Approval\Http\Controllers\ApprovalSlaController;
use Domains\Approval\Http\Controllers\ApprovalTaskController;
use Domains\Approval\Http\Controllers\RequisitionApprovalController;
use Domains\Attachment\Http\Controllers\AttachmentFileController;
use Domains\Attachment\Http\Controllers\RequisitionAttachmentController;
use Domains\Collaboration\Http\Controllers\RequisitionCommentController;
use Domains\Project\Http\Controllers\ProcurementProjectController;
use Domains\Project\Http\Controllers\ProjectActivityController;
use Domains\Project\Http\Controllers\ProjectRequisitionController;
use Domains\Quotation\Http\Controllers\RfqController;
use Domains\Quotation\Http\Controllers\RfqAwardRecommendationController;
use Domains\Quotation\Http\Controllers\RfqInvitationController;
use Domains\Quotation\Http\Controllers\RfqScorecardController;
use Domains\Quotation\Http\Controllers\QuotationComparisonController;
use Domains\Quotation\Http\Controllers\QuotationNormalizationController;
use Domains\Quotation\Http\Controllers\QuotationScoringTemplateController;
use Domains\Quotation\Http\Controllers\QuotationVersionNormalizationController;
use Domains\Quotation\Http\Controllers\QuotationVersionController;
use Domains\Quotation\Http\Controllers\RfqInvitationQuotationController;
use Domains\Quotation\Http\Controllers\RfqInvitationPortalController;
use Domains\Quotation\Http\Controllers\SourcingIntakeReviewController;
use Domains\Quotation\Http\Controllers\VendorPortalQuotationController;
use Domains\Quotation\Http\Controllers\VendorPortalQuotationVersionController;
use Domains\Requisition\Http\Controllers\RequisitionActivityController;
use Domains\Requisition\Http\Controllers\RequisitionController;
use Domains\Requisition\Http\Controllers\RequisitionIntakeOptionsController;
use Domains\Requisition\Http\Controllers\RequisitionItemSuggestionController;
use Domains\Requisition\Http\Controllers\RequisitionTemplateController;
use Domains\Search\Http\Controllers\SearchController;
use Domains\Vendor\Http\Controllers\VendorPickerController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

Route::get('/health', static function (): JsonResponse {
    return response()->json([
        'status' => 'ok',
        'service' => 'cognify-api',
    ]);
});

// Public auth routes
Route::middleware('throttle:5,1')->group(function (): void {
    Route::post('/auth/login', [AuthenticatedSessionController::class, 'store']);
    Route::post('/auth/forgot-password', [AuthenticatedSessionController::class, 'forgotPassword']);
});

Route::get('/vendor-portal/rfq-invitations/{token}', [RfqInvitationPortalController::class, 'show'])
    ->middleware('throttle:60,1');
Route::get('/vendor-portal/rfq-invitations/{token}/quotation', [VendorPortalQuotationController::class, 'show'])
    ->middleware('throttle:60,1');
Route::get('/vendor-portal/rfq-invitations/{token}/quotation/versions', [VendorPortalQuotationVersionController::class, 'index'])
    ->middleware('throttle:60,1');
Route::post('/vendor-portal/rfq-invitations/{token}/quotation/versions', [VendorPortalQuotationVersionController::class, 'store'])
    ->middleware('throttle:60,1');
Route::post('/vendor-portal/rfq-invitations/{token}/quotation/attachments', [VendorPortalQuotationController::class, 'storeAttachment'])
    ->middleware('throttle:60,1');
Route::put('/vendor-portal/rfq-invitations/{token}/quotation/manual-entry', [VendorPortalQuotationController::class, 'saveManualEntry'])
    ->middleware('throttle:60,1');

// Protected routes
Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/auth/logout', [AuthenticatedSessionController::class, 'destroy']);
    Route::get('/me', [CurrentUserController::class, 'show']);
    Route::post('/tenants/current', [CurrentTenantController::class, 'store']);

    Route::middleware(ResolveCurrentTenant::class)->group(function (): void {
        Route::patch('/me/profile', [UserProfileController::class, 'update']);
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);

        Route::get('/search', [SearchController::class, 'index'])->middleware('throttle:60,1');
        Route::get('/system/status', [SystemStatusController::class, 'show'])->middleware('throttle:30,1');

        Route::get('/approval-policies', [ApprovalPolicyController::class, 'index']);
        Route::post('/approval-policies', [ApprovalPolicyController::class, 'store']);
        Route::post('/approval-policies/preview', [ApprovalPolicyController::class, 'preview']);
        Route::get('/approval-policies/{approvalPolicy}', [ApprovalPolicyController::class, 'show']);
        Route::patch('/approval-policies/{approvalPolicy}', [ApprovalPolicyController::class, 'update']);
        Route::post('/approval-policies/{approvalPolicy}/versions', [ApprovalPolicyVersionController::class, 'store']);
        Route::post('/approval-policy-versions/{approvalPolicyVersion}/publish', [ApprovalPolicyVersionController::class, 'publish']);
        Route::post('/approval-policy-versions/{approvalPolicyVersion}/retire', [ApprovalPolicyVersionController::class, 'retire']);
        Route::get('/approval-delegations', [ApprovalDelegationController::class, 'index']);
        Route::get('/approval-delegations/delegate-candidates', [ApprovalDelegationController::class, 'candidates']);
        Route::post('/approval-delegations', [ApprovalDelegationController::class, 'store']);
        Route::patch('/approval-delegations/{approvalDelegation}', [ApprovalDelegationController::class, 'update']);
        Route::post('/approval-delegations/{approvalDelegation}/cancel', [ApprovalDelegationController::class, 'cancel']);
        Route::get('/approvals/sla-summary', [ApprovalSlaController::class, 'summary']);
        Route::get('/approval-instances/{approvalInstance}', [ApprovalSlaController::class, 'showInstance']);
        Route::get('/approval-tasks', [ApprovalTaskController::class, 'index']);
        Route::get('/approval-tasks/{approvalTask}', [ApprovalTaskController::class, 'show']);
        Route::post('/approval-tasks/{approvalTask}/view', [ApprovalTaskController::class, 'view']);
        Route::post('/approval-tasks/{approvalTask}/approve', [ApprovalTaskController::class, 'approve']);
        Route::post('/approval-tasks/{approvalTask}/reject', [ApprovalTaskController::class, 'reject']);
        Route::post('/approval-tasks/{approvalTask}/request-changes', [ApprovalTaskController::class, 'requestChanges']);
        Route::post('/approval-tasks/{approvalTask}/delegate', [ApprovalTaskController::class, 'delegate']);

        Route::get('/sourcing/intake-reviews', [SourcingIntakeReviewController::class, 'index']);
        Route::get('/sourcing/intake-reviews/{review}', [SourcingIntakeReviewController::class, 'show']);
        Route::post('/sourcing/intake-reviews/{review}/claim', [SourcingIntakeReviewController::class, 'claim']);
        Route::post('/sourcing/intake-reviews/{review}/reassign', [SourcingIntakeReviewController::class, 'reassign']);
        Route::patch('/sourcing/intake-reviews/{review}', [SourcingIntakeReviewController::class, 'update']);
        Route::post('/sourcing/intake-reviews/{review}/decision', [SourcingIntakeReviewController::class, 'decision']);
        Route::post('/sourcing/intake-reviews/{review}/close', [SourcingIntakeReviewController::class, 'close']);
        Route::post('/sourcing/intake-reviews/{review}/rfq', [RfqController::class, 'storeForIntake']);
        Route::middleware(RequireTenantHeader::class)->group(function (): void {
            Route::prefix('quotation-scoring')->group(function (): void {
                Route::get('/templates', [QuotationScoringTemplateController::class, 'index']);
                Route::post('/templates', [QuotationScoringTemplateController::class, 'store']);
                Route::get('/templates/{quotationScoringTemplate}', [QuotationScoringTemplateController::class, 'show']);
                Route::patch('/templates/{quotationScoringTemplate}', [QuotationScoringTemplateController::class, 'update']);
                Route::post('/templates/{quotationScoringTemplate}/deactivate', [QuotationScoringTemplateController::class, 'deactivate']);
            });
            Route::get('/rfqs/{rfq}/scorecard', [RfqScorecardController::class, 'show']);
            Route::post('/rfqs/{rfq}/scorecard', [RfqScorecardController::class, 'store']);
            Route::patch('/rfqs/{rfq}/scorecard/scores', [RfqScorecardController::class, 'updateScores']);
            Route::post('/rfqs/{rfq}/scorecard/complete', [RfqScorecardController::class, 'complete']);
            Route::post('/rfqs/{rfq}/scorecard/reopen', [RfqScorecardController::class, 'reopen']);
            Route::get('/rfqs/{rfq}/award-recommendation', [RfqAwardRecommendationController::class, 'show']);
            Route::put('/rfqs/{rfq}/award-recommendation', [RfqAwardRecommendationController::class, 'save']);
            Route::post('/rfqs/{rfq}/award-recommendation/submit', [RfqAwardRecommendationController::class, 'submit']);
            Route::post('/rfqs/{rfq}/award-recommendation/withdraw', [RfqAwardRecommendationController::class, 'withdraw']);
        });
        Route::get('/rfqs/{rfq}', [RfqController::class, 'show']);
        Route::patch('/rfqs/{rfq}', [RfqController::class, 'update']);
        Route::post('/rfqs/{rfq}/cancel', [RfqController::class, 'cancel']);
        Route::get('/rfqs/{rfq}/comparison', [QuotationComparisonController::class, 'show']);
        Route::post('/rfqs/{rfq}/comparison/notes', [QuotationComparisonController::class, 'storeNote']);
        Route::patch('/rfqs/{rfq}/comparison/notes/{note}', [QuotationComparisonController::class, 'updateNote']);
        Route::delete('/rfqs/{rfq}/comparison/notes/{note}', [QuotationComparisonController::class, 'deleteNote']);
        Route::get('/vendors', [VendorPickerController::class, 'index']);
        Route::get('/rfqs/{rfq}/invitations', [RfqInvitationController::class, 'index']);
        Route::post('/rfqs/{rfq}/invitations', [RfqInvitationController::class, 'store']);
        Route::post('/rfq-invitations/{invitation}/resend', [RfqInvitationController::class, 'resend']);
        Route::post('/rfq-invitations/{invitation}/cancel', [RfqInvitationController::class, 'cancel']);
        Route::patch('/rfq-invitations/{invitation}/status', [RfqInvitationController::class, 'status']);
        Route::post('/rfq-invitations/{invitation}/portal-link', [RfqInvitationPortalController::class, 'regenerate']);
        Route::get('/rfq-invitations/{invitation}/quotation', [RfqInvitationQuotationController::class, 'show']);
        Route::put('/rfq-invitations/{invitation}/quotation/manual-entry', [RfqInvitationQuotationController::class, 'saveManualEntryForInvitation']);
        Route::post('/rfq-invitations/{invitation}/quotation/attachments', [RfqInvitationQuotationController::class, 'storeAttachment']);
        Route::get('/quotations/{quotation}/attachments', [RfqInvitationQuotationController::class, 'attachments']);
        Route::put('/quotations/{quotation}/manual-entry', [RfqInvitationQuotationController::class, 'saveManualEntry']);
        Route::get('/quotations/{quotation}/versions', [QuotationVersionController::class, 'index']);
        Route::get('/quotations/{quotation}/versions/{version}', [QuotationVersionController::class, 'show']);
        Route::post('/quotations/{quotation}/versions', [QuotationVersionController::class, 'store']);
        Route::get('/quotation-normalizations', [QuotationNormalizationController::class, 'index']);
        Route::get('/quotation-normalizations/{normalization}', [QuotationNormalizationController::class, 'show']);
        Route::post('/quotation-normalizations/{normalization}/corrections', [QuotationNormalizationController::class, 'corrections']);
        Route::post('/quotation-normalizations/{normalization}/line-mappings', [QuotationNormalizationController::class, 'lineMappings']);
        Route::post('/quotation-normalizations/{normalization}/approve', [QuotationNormalizationController::class, 'approve']);
        Route::post('/quotation-normalizations/{normalization}/approve-with-warnings', [QuotationNormalizationController::class, 'approveWithWarnings']);
        Route::post('/quotation-normalizations/{normalization}/revisions', [QuotationNormalizationController::class, 'revision']);
        Route::post('/quotation-versions/{version}/normalization/retry', [QuotationVersionNormalizationController::class, 'retry']);

        Route::get('/requisition-templates', [RequisitionTemplateController::class, 'index']);
        Route::get('/requisition-line-item-suggestions', [RequisitionItemSuggestionController::class, 'index']);
        Route::get('/requisition-intake-options', RequisitionIntakeOptionsController::class);

        Route::get('/projects', [ProcurementProjectController::class, 'index']);
        Route::post('/projects', [ProcurementProjectController::class, 'store']);
        Route::get('/projects/{project}', [ProcurementProjectController::class, 'show']);
        Route::patch('/projects/{project}', [ProcurementProjectController::class, 'update']);
        Route::post('/projects/{project}/activate', [ProcurementProjectController::class, 'activate']);
        Route::post('/projects/{project}/hold', [ProcurementProjectController::class, 'hold']);
        Route::post('/projects/{project}/resume', [ProcurementProjectController::class, 'resume']);
        Route::post('/projects/{project}/complete', [ProcurementProjectController::class, 'complete']);
        Route::post('/projects/{project}/cancel', [ProcurementProjectController::class, 'cancel']);
        Route::get('/projects/{project}/activity', [ProjectActivityController::class, 'index']);
        Route::get('/projects/{project}/requisitions', [ProjectRequisitionController::class, 'index']);
        Route::post('/projects/{project}/requisitions', [ProjectRequisitionController::class, 'store']);
        Route::delete('/projects/{project}/requisitions/{requisition}', [ProjectRequisitionController::class, 'destroy']);

        // Existing requisition routes
        Route::get('/requisitions', [RequisitionController::class, 'index']);
        Route::post('/requisitions', [RequisitionController::class, 'store']);
        Route::get('/requisitions/{requisition}', [RequisitionController::class, 'show']);
        Route::post('/requisitions/{requisition}/sourcing-intake', [SourcingIntakeReviewController::class, 'storeForRequisition']);
        Route::get('/requisitions/{requisition}/approval-preview', [RequisitionApprovalController::class, 'preview']);
        Route::post('/requisitions/{requisition}/route-approval', [RequisitionApprovalController::class, 'route']);
        Route::get('/requisitions/{requisition}/approval-summary', [RequisitionApprovalController::class, 'summary']);
        Route::patch('/requisitions/{requisition}', [RequisitionController::class, 'update']);
        Route::post('/requisitions/{requisition}/apply-template', [RequisitionController::class, 'applyTemplate']);
        Route::post('/requisitions/{requisition}/submit', [RequisitionController::class, 'submit']);
        Route::post('/requisitions/{requisition}/request-changes', [RequisitionController::class, 'requestChanges']);
        Route::post('/requisitions/{requisition}/resubmit', [RequisitionController::class, 'resubmit']);
        Route::post('/requisitions/{requisition}/withdraw', [RequisitionController::class, 'withdraw']);
        Route::post('/requisitions/{requisition}/cancel', [RequisitionController::class, 'cancel']);
        Route::get('/requisitions/{requisition}/activity', [RequisitionActivityController::class, 'index']);
        Route::get('/requisitions/{requisition}/comments', [RequisitionCommentController::class, 'index']);
        Route::post('/requisitions/{requisition}/comments', [RequisitionCommentController::class, 'store']);
        Route::get('/requisitions/{requisition}/mention-candidates', [RequisitionCommentController::class, 'mentionCandidates']);
        Route::get('/requisitions/{requisition}/attachments', [RequisitionAttachmentController::class, 'index']);
        Route::post('/requisitions/{requisition}/attachments', [RequisitionAttachmentController::class, 'store']);
        Route::get('/attachments/{attachment}/preview', [AttachmentFileController::class, 'preview']);
        Route::get('/attachments/{attachment}/download', [AttachmentFileController::class, 'download']);
        Route::delete('/attachments/{attachment}', [AttachmentFileController::class, 'destroy']);
        Route::get('/audit/events', [AuditEventController::class, 'index'])->middleware('throttle:60,1');
    });
});
