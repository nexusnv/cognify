<?php

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

Route::get('/health', static function (): JsonResponse {
    return response()->json([
        'status' => 'ok',
        'service' => 'cognify-api',
    ]);
});
