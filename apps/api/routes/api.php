<?php

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;
use App\Http\Middleware\ResolveCurrentTenant;
use Domains\Requisition\Http\Controllers\RequisitionActivityController;
use Domains\Requisition\Http\Controllers\RequisitionController;

Route::get('/health', static function (): JsonResponse {
    return response()->json([
        'status' => 'ok',
        'service' => 'cognify-api',
    ]);
});

Route::middleware(['auth:sanctum', ResolveCurrentTenant::class])->group(function (): void {
    Route::get('/requisitions', [RequisitionController::class, 'index']);
    Route::post('/requisitions', [RequisitionController::class, 'store']);
    Route::get('/requisitions/{requisition}', [RequisitionController::class, 'show']);
    Route::patch('/requisitions/{requisition}', [RequisitionController::class, 'update']);
    Route::post('/requisitions/{requisition}/submit', [RequisitionController::class, 'submit']);
    Route::get('/requisitions/{requisition}/activity', [RequisitionActivityController::class, 'index']);
});
