<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PixController;
use App\Http\Controllers\Api\WithdrawController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes (for testing without auth)
/**
 * @OA\Get(
 *     path="/health",
 *     summary="Health Check",
 *     description="Verifica se a API está funcionando corretamente",
 *     operationId="healthCheck",
 *     tags={"Health"},
 *     @OA\Response(
 *         response=200,
 *         description="API está funcionando",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="string", example="ok"),
 *             @OA\Property(property="timestamp", type="string", format="datetime", example="2025-11-19T04:00:00+00:00")
 *         )
 *     )
 * )
 */
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
    ]);
});

// Auth routes with rate limiting (5 requests per minute)
Route::middleware(['throttle:5,1'])->group(function () {
    Route::post('/auth/token', [AuthController::class, 'token']);
});

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // User info
    Route::get('/user', function (Request $request) {
        return response()->json([
            'data' => $request->user()->load('subacquirer'),
        ]);
    });

    // PIX routes with rate limiting (60 requests per minute for creation)
    Route::prefix('pix')->group(function () {
        Route::get('/', [PixController::class, 'index']);
        Route::post('/', [PixController::class, 'store'])->middleware('throttle:60,1');
        Route::get('/{id}', [PixController::class, 'show']);
    });

    // Withdraw routes with rate limiting (30 requests per minute for creation)
    Route::prefix('withdraw')->group(function () {
        Route::get('/', [WithdrawController::class, 'index']);
        Route::post('/', [WithdrawController::class, 'store'])->middleware('throttle:30,1');
        Route::get('/{id}', [WithdrawController::class, 'show']);
    });
});
