<?php

use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::prefix('v1')->group(function () {
    // Public routes (authentication, etc.)
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    });

    // Protected routes (require authentication)
    Route::middleware('auth:sanctum')->group(function () {
        // Auth routes
        Route::prefix('auth')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
        });

        // Orders
        Route::apiResource('orders', App\Http\Controllers\Api\V1\OrderController::class);
        Route::post('orders/{order}/status', [App\Http\Controllers\Api\V1\OrderController::class, 'updateStatus']);
        Route::post('orders/{order}/cancel', [App\Http\Controllers\Api\V1\OrderController::class, 'cancel']);

        // Products
        Route::apiResource('products', App\Http\Controllers\Api\V1\ProductController::class);

        // Stock Management
        Route::prefix('products/{product}')->group(function () {
            Route::get('stock/movements', [App\Http\Controllers\Api\V1\StockController::class, 'movements']);
            Route::post('stock/add', [App\Http\Controllers\Api\V1\StockController::class, 'add']);
            Route::post('stock/adjust', [App\Http\Controllers\Api\V1\StockController::class, 'adjust']);
        });

        // Companies
        Route::apiResource('companies', App\Http\Controllers\Api\V1\CompanyController::class);
    });
});

