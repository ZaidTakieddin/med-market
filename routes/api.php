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

        // Invoices
        Route::apiResource('invoices', App\Http\Controllers\Api\V1\InvoiceController::class)->only(['index', 'show']);
        Route::get('orders/{order}/invoice', [App\Http\Controllers\Api\V1\InvoiceController::class, 'getByOrder']);
        Route::get('invoices/{invoice}/download', [App\Http\Controllers\Api\V1\InvoiceController::class, 'download']);
        Route::post('invoices/{invoice}/send', [App\Http\Controllers\Api\V1\InvoiceController::class, 'markAsSent']);
        Route::post('invoices/{invoice}/pay', [App\Http\Controllers\Api\V1\InvoiceController::class, 'markAsPaid']);

        // Categories (admin managed, but list available to all)
        Route::apiResource('categories', App\Http\Controllers\Api\V1\CategoryController::class);

        // Manufacturers (admin managed, but list available to all)
        Route::apiResource('manufacturers', App\Http\Controllers\Api\V1\ManufacturerController::class);

        // Cities (admin managed, but list available to all)
        Route::apiResource('cities', App\Http\Controllers\Api\V1\CityController::class);

        // Regions (admin managed, but list available to all)
        Route::apiResource('regions', App\Http\Controllers\Api\V1\RegionController::class);

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

        // Cart Management
        Route::prefix('cart')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\V1\CartController::class, 'index']);
            Route::post('/items', [App\Http\Controllers\Api\V1\CartController::class, 'addItem']);
            Route::put('/items/{id}', [App\Http\Controllers\Api\V1\CartController::class, 'updateItem']);
            Route::delete('/items/{id}', [App\Http\Controllers\Api\V1\CartController::class, 'removeItem']);
            Route::delete('/', [App\Http\Controllers\Api\V1\CartController::class, 'clear']);
            Route::post('/checkout', [App\Http\Controllers\Api\V1\CartController::class, 'checkout']);
        });

        // Offers
        Route::apiResource('offers', App\Http\Controllers\Api\V1\OfferController::class);

        // Drivers
        Route::apiResource('drivers', App\Http\Controllers\Api\V1\DriverController::class);
        Route::get('drivers/{id}/orders', [App\Http\Controllers\Api\V1\DriverController::class, 'orders']);
        Route::get('drivers/{id}/regions', [App\Http\Controllers\Api\V1\DriverController::class, 'regions']);
        Route::post('drivers/{id}/regions', [App\Http\Controllers\Api\V1\DriverController::class, 'assignRegions']);
        Route::delete('drivers/{id}/regions/{region_id}', [App\Http\Controllers\Api\V1\DriverController::class, 'removeRegion']);

        // Profile
        Route::prefix('profile')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\V1\ProfileController::class, 'show']);
            Route::put('/', [App\Http\Controllers\Api\V1\ProfileController::class, 'update']);
            Route::put('/password', [App\Http\Controllers\Api\V1\ProfileController::class, 'changePassword']);
            Route::put('/company', [App\Http\Controllers\Api\V1\ProfileController::class, 'updateCompany']);
        });

        // Order driver assignment
        Route::post('orders/{order}/assign-driver', [App\Http\Controllers\Api\V1\OrderController::class, 'assignDriver']);
    });
});

