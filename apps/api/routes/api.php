<?php

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;
use App\Auth\Http\Controllers\AuthenticatedSessionController;
use App\Auth\Http\Controllers\CurrentTenantController;
use App\Auth\Http\Controllers\CurrentUserController;
use App\Auth\Http\Controllers\UserProfileController;
use App\Http\Middleware\ResolveCurrentTenant;
use Domains\Requisition\Http\Controllers\RequisitionActivityController;
use Domains\Requisition\Http\Controllers\RequisitionController;

Route::get('/health', static function (): JsonResponse {
    return response()->json([
        'status' => 'ok',
        'service' => 'cognify-api',
    ]);
});

// Public auth routes
Route::post('/auth/login', [AuthenticatedSessionController::class, 'store']);
Route::post('/auth/forgot-password', [AuthenticatedSessionController::class, 'forgotPassword']);

// Protected routes
Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/auth/logout', [AuthenticatedSessionController::class, 'destroy']);
    Route::post('/tenants/current', [CurrentTenantController::class, 'store']);

    Route::middleware(ResolveCurrentTenant::class)->group(function (): void {
        Route::get('/me', [CurrentUserController::class, 'show']);
        Route::patch('/me/profile', [UserProfileController::class, 'update']);

        // Existing requisition routes
        Route::get('/requisitions', [RequisitionController::class, 'index']);
        Route::post('/requisitions', [RequisitionController::class, 'store']);
        Route::get('/requisitions/{requisition}', [RequisitionController::class, 'show']);
        Route::patch('/requisitions/{requisition}', [RequisitionController::class, 'update']);
        Route::post('/requisitions/{requisition}/submit', [RequisitionController::class, 'submit']);
        Route::get('/requisitions/{requisition}/activity', [RequisitionActivityController::class, 'index']);
    });
});
