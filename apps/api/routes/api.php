<?php

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;
use App\Auth\Http\Controllers\AuthenticatedSessionController;
use App\Auth\Http\Controllers\CurrentTenantController;
use App\Auth\Http\Controllers\CurrentUserController;
use App\Auth\Http\Controllers\UserProfileController;
use App\Audit\Http\Controllers\AuditEventController;
use App\Http\Middleware\ResolveCurrentTenant;
use App\Notifications\Http\Controllers\NotificationController;
use App\Observability\SystemStatus\Http\Controllers\SystemStatusController;
use Domains\Attachment\Http\Controllers\AttachmentFileController;
use Domains\Attachment\Http\Controllers\RequisitionAttachmentController;
use Domains\Requisition\Http\Controllers\RequisitionActivityController;
use Domains\Requisition\Http\Controllers\RequisitionController;
use Domains\Requisition\Http\Controllers\RequisitionIntakeOptionsController;
use Domains\Requisition\Http\Controllers\RequisitionItemSuggestionController;
use Domains\Requisition\Http\Controllers\RequisitionTemplateController;
use Domains\Search\Http\Controllers\SearchController;

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

// Protected routes
Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/auth/logout', [AuthenticatedSessionController::class, 'destroy']);
    Route::post('/tenants/current', [CurrentTenantController::class, 'store']);

    Route::middleware(ResolveCurrentTenant::class)->group(function (): void {
        Route::get('/me', [CurrentUserController::class, 'show']);
        Route::patch('/me/profile', [UserProfileController::class, 'update']);
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);

        Route::get('/search', [SearchController::class, 'index'])->middleware('throttle:60,1');
        Route::get('/system/status', [SystemStatusController::class, 'show'])->middleware('throttle:30,1');

        Route::get('/requisition-templates', [RequisitionTemplateController::class, 'index']);
        Route::get('/requisition-line-item-suggestions', [RequisitionItemSuggestionController::class, 'index']);
        Route::get('/requisition-intake-options', RequisitionIntakeOptionsController::class);

        // Existing requisition routes
        Route::get('/requisitions', [RequisitionController::class, 'index']);
        Route::post('/requisitions', [RequisitionController::class, 'store']);
        Route::get('/requisitions/{requisition}', [RequisitionController::class, 'show']);
        Route::patch('/requisitions/{requisition}', [RequisitionController::class, 'update']);
        Route::post('/requisitions/{requisition}/apply-template', [RequisitionController::class, 'applyTemplate']);
        Route::post('/requisitions/{requisition}/submit', [RequisitionController::class, 'submit']);
        Route::get('/requisitions/{requisition}/activity', [RequisitionActivityController::class, 'index']);
        Route::get('/requisitions/{requisition}/attachments', [RequisitionAttachmentController::class, 'index']);
        Route::post('/requisitions/{requisition}/attachments', [RequisitionAttachmentController::class, 'store']);
        Route::get('/attachments/{attachment}/preview', [AttachmentFileController::class, 'preview']);
        Route::get('/attachments/{attachment}/download', [AttachmentFileController::class, 'download']);
        Route::delete('/attachments/{attachment}', [AttachmentFileController::class, 'destroy']);
        Route::get('/audit/events', [AuditEventController::class, 'index'])->middleware('throttle:60,1');
    });
});
