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
        Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    });

    // Protected routes (require authentication, rate-limited per account)
    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        // Enums (system constants for dropdowns, forms)
        Route::get('enums', [App\Http\Controllers\Api\V1\EnumsController::class, 'index']);

        // Dashboard analytics
        Route::get('dashboard', [App\Http\Controllers\Api\V1\DashboardController::class, 'index']);

        // Platform analytics (admin)
        Route::get('analytics/overview', [App\Http\Controllers\Api\V1\AnalyticsController::class, 'overview']);
        Route::get('analytics/orders', [App\Http\Controllers\Api\V1\AnalyticsController::class, 'orderTrends']);
        Route::get('analytics/drivers', [App\Http\Controllers\Api\V1\AnalyticsController::class, 'drivers']);

        // Autocomplete suggestions
        Route::get('search/suggestions', [App\Http\Controllers\Api\V1\SearchController::class, 'suggestions']);

        // Wishlist
        Route::post('wishlist/items/{product}/move-to-cart', [App\Http\Controllers\Api\V1\WishlistController::class, 'moveToCart']);
        Route::delete('wishlist/items/{product}', [App\Http\Controllers\Api\V1\WishlistController::class, 'removeItem']);
        Route::post('wishlist/items', [App\Http\Controllers\Api\V1\WishlistController::class, 'addItem']);
        Route::get('wishlist', [App\Http\Controllers\Api\V1\WishlistController::class, 'index']);

        // Auth routes
        Route::prefix('auth')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
        });

        // User management (admin)
        Route::apiResource('users', App\Http\Controllers\Api\V1\UserController::class);

        // Order templates (buyer companies)
        Route::post('order-templates/{orderTemplate}/reorder', [App\Http\Controllers\Api\V1\OrderTemplateController::class, 'reorder']);
        Route::apiResource('order-templates', App\Http\Controllers\Api\V1\OrderTemplateController::class)
            ->parameters(['order-templates' => 'orderTemplate']);

        // Notifications
        Route::get('notifications/unread-count', [App\Http\Controllers\Api\V1\NotificationController::class, 'unreadCount']);
        Route::post('notifications/mark-all-read', [App\Http\Controllers\Api\V1\NotificationController::class, 'markAllRead']);
        Route::post('notifications/{id}/mark-read', [App\Http\Controllers\Api\V1\NotificationController::class, 'markRead']);
        Route::get('notifications', [App\Http\Controllers\Api\V1\NotificationController::class, 'index']);

        // Order exports — registered before the orders apiResource so
        // GET /orders/exports can't be captured by GET /orders/{order}
        Route::get('orders/exports', [App\Http\Controllers\Api\V1\OrderExportController::class, 'index']);
        Route::post('orders/exports', [App\Http\Controllers\Api\V1\OrderExportController::class, 'store']);
        Route::get('orders/exports/{id}', [App\Http\Controllers\Api\V1\OrderExportController::class, 'show']);
        Route::get('orders/exports/{id}/download', [App\Http\Controllers\Api\V1\OrderExportController::class, 'download']);

        // Orders
        Route::apiResource('orders', App\Http\Controllers\Api\V1\OrderController::class)->except(['update', 'destroy']);
        Route::post('orders/{order}/status', [App\Http\Controllers\Api\V1\OrderController::class, 'updateStatus']);
        Route::post('orders/{order}/cancel', [App\Http\Controllers\Api\V1\OrderController::class, 'cancel']);

        // Invoices
        Route::apiResource('invoices', App\Http\Controllers\Api\V1\InvoiceController::class)->only(['index', 'show']);
        Route::get('orders/{order}/invoice', [App\Http\Controllers\Api\V1\InvoiceController::class, 'getByOrder']);
        Route::get('invoices/{invoice}/download', [App\Http\Controllers\Api\V1\InvoiceController::class, 'download']);
        Route::post('invoices/{invoice}/send', [App\Http\Controllers\Api\V1\InvoiceController::class, 'markAsSent']);
        Route::post('invoices/{invoice}/pay', [App\Http\Controllers\Api\V1\InvoiceController::class, 'markAsPaid']);

        // Delivery proofs
        Route::get('orders/{order}/delivery-proof', [App\Http\Controllers\Api\V1\DeliveryProofController::class, 'index']);
        Route::post('orders/{order}/delivery-proof', [App\Http\Controllers\Api\V1\DeliveryProofController::class, 'store']);

        // Categories (admin managed, but list available to all)
        Route::apiResource('categories', App\Http\Controllers\Api\V1\CategoryController::class);

        // Manufacturers (admin managed, but list available to all)
        Route::apiResource('manufacturers', App\Http\Controllers\Api\V1\ManufacturerController::class);

        // Cities (admin managed, but list available to all)
        Route::apiResource('cities', App\Http\Controllers\Api\V1\CityController::class);

        // Regions (admin managed, but list available to all)
        Route::apiResource('regions', App\Http\Controllers\Api\V1\RegionController::class);

        // Product Import (must be registered before the products apiResource so
        // `products/{product}` does not capture these paths)
        Route::get('products/import-sample', [App\Http\Controllers\Api\V1\ProductImportController::class, 'sample']);
        Route::post('products/import', [App\Http\Controllers\Api\V1\ProductImportController::class, 'store']);
        Route::get('products/imports', [App\Http\Controllers\Api\V1\ProductImportController::class, 'index']);
        Route::get('products/imports/{import}', [App\Http\Controllers\Api\V1\ProductImportController::class, 'show']);

        // Products
        Route::apiResource('products', App\Http\Controllers\Api\V1\ProductController::class);

        // Bulk stock (registered before the products/{product} stock prefix)
        Route::post('products/stock/bulk', [App\Http\Controllers\Api\V1\StockController::class, 'bulk']);

        // Stock Management
        Route::prefix('products/{product}')->group(function () {
            Route::get('stock/movements', [App\Http\Controllers\Api\V1\StockController::class, 'movements']);
            Route::post('stock/add', [App\Http\Controllers\Api\V1\StockController::class, 'add']);
            Route::post('stock/adjust', [App\Http\Controllers\Api\V1\StockController::class, 'adjust']);
            Route::get('batches', [App\Http\Controllers\Api\V1\StockController::class, 'batches']);
            Route::post('transfer', [App\Http\Controllers\Api\V1\StockController::class, 'transfer']);
        });

        // Product Images (literal `reorder` is registered before `{image}` so it is not captured)
        Route::prefix('products/{product}')->group(function () {
            Route::get('images', [App\Http\Controllers\Api\V1\ProductImageController::class, 'index']);
            Route::post('images', [App\Http\Controllers\Api\V1\ProductImageController::class, 'store']);
            Route::patch('images/reorder', [App\Http\Controllers\Api\V1\ProductImageController::class, 'reorder']);
            Route::patch('images/{image}/primary', [App\Http\Controllers\Api\V1\ProductImageController::class, 'setPrimary']);
            Route::delete('images/{image}', [App\Http\Controllers\Api\V1\ProductImageController::class, 'destroy']);
        });

        // Companies
        Route::apiResource('companies', App\Http\Controllers\Api\V1\CompanyController::class);

        // Company Logo
        Route::post('companies/{company}/logo', [App\Http\Controllers\Api\V1\CompanyLogoController::class, 'store']);
        Route::delete('companies/{company}/logo', [App\Http\Controllers\Api\V1\CompanyLogoController::class, 'destroy']);

        // Cart Management
        Route::prefix('cart')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\V1\CartController::class, 'index']);
            Route::post('/items', [App\Http\Controllers\Api\V1\CartController::class, 'addItem']);
            Route::put('/items/{id}', [App\Http\Controllers\Api\V1\CartController::class, 'updateItem']);
            Route::get('/items/{id}/offers', [App\Http\Controllers\Api\V1\CartController::class, 'getAvailableOffers']);
            Route::post('/items/{id}/apply-offer', [App\Http\Controllers\Api\V1\CartController::class, 'applyOffer']);
            Route::post('/items/{id}/remove-offer', [App\Http\Controllers\Api\V1\CartController::class, 'removeOffer']);
            Route::delete('/items/{id}', [App\Http\Controllers\Api\V1\CartController::class, 'removeItem']);
            Route::delete('/', [App\Http\Controllers\Api\V1\CartController::class, 'clear']);
            Route::post('/checkout', [App\Http\Controllers\Api\V1\CartController::class, 'checkout']);
            Route::post('/checkout-supplier', [App\Http\Controllers\Api\V1\CartController::class, 'checkoutSupplier']);
        });

        // Offers
        Route::apiResource('offers', App\Http\Controllers\Api\V1\OfferController::class);

        // Drivers
        Route::apiResource('drivers', App\Http\Controllers\Api\V1\DriverController::class);
        // Location ping must be defined before {driver} bindings could capture "location"
        Route::post('drivers/location', [App\Http\Controllers\Api\V1\DriverController::class, 'pingLocation']);
        Route::get('drivers/{driver}/orders', [App\Http\Controllers\Api\V1\DriverController::class, 'orders']);
        Route::get('drivers/{driver}/regions', [App\Http\Controllers\Api\V1\DriverController::class, 'regions']);
        Route::post('drivers/{driver}/regions', [App\Http\Controllers\Api\V1\DriverController::class, 'assignRegions']);
        Route::post('drivers/{driver}/region-plan', [App\Http\Controllers\Api\V1\DriverController::class, 'setRegionPlan']);
        Route::put('drivers/{driver}/holidays', [App\Http\Controllers\Api\V1\DriverController::class, 'setHolidays']);
        Route::delete('drivers/{driver}/regions/{region_id}', [App\Http\Controllers\Api\V1\DriverController::class, 'removeRegion']);
        Route::get('drivers/{driver}/location', [App\Http\Controllers\Api\V1\DriverController::class, 'latestLocation']);

        // Profile
        Route::prefix('profile')->group(function () {
            Route::get('/', [App\Http\Controllers\Api\V1\ProfileController::class, 'show']);
            Route::put('/', [App\Http\Controllers\Api\V1\ProfileController::class, 'update']);
            Route::put('/password', [App\Http\Controllers\Api\V1\ProfileController::class, 'changePassword']);
            Route::put('/company', [App\Http\Controllers\Api\V1\ProfileController::class, 'updateCompany']);
        });

        // Order driver assignment
        Route::post('orders/{order}/assign-driver', [App\Http\Controllers\Api\V1\OrderController::class, 'assignDriver']);

        // Buyer-Supplier Relationships & Credit Management
        Route::apiResource('buyer-supplier-relationships', App\Http\Controllers\Api\V1\BuyerSupplierRelationshipController::class)->parameters(['buyer-supplier-relationships' => 'relationship']);
        Route::post('buyer-supplier-relationships/{relationship}/approve', [App\Http\Controllers\Api\V1\BuyerSupplierRelationshipController::class, 'approve']);
        Route::post('buyer-supplier-relationships/{relationship}/reject', [App\Http\Controllers\Api\V1\BuyerSupplierRelationshipController::class, 'reject']);
        Route::post('buyer-supplier-relationships/{relationship}/adjust-credit', [App\Http\Controllers\Api\V1\BuyerSupplierRelationshipController::class, 'adjustCredit']);
        Route::get('buyer-supplier-relationships/{relationship}/check-credit', [App\Http\Controllers\Api\V1\BuyerSupplierRelationshipController::class, 'checkCredit']);

        // Price Lists (Custom Per-Buyer Pricing)
        Route::get('price-lists/for-buyer/{buyerCompanyId}', [App\Http\Controllers\Api\V1\PriceListController::class, 'forBuyer']);
        Route::apiResource('price-lists', App\Http\Controllers\Api\V1\PriceListController::class)->parameters(['price-lists' => 'priceList']);

        // Salesmen
        Route::get('salesmen', [App\Http\Controllers\Api\V1\SalesmanController::class, 'index']);
        Route::get('salesmen/{salesman}', [App\Http\Controllers\Api\V1\SalesmanController::class, 'show']);
        Route::get('salesmen/{salesman}/regions', [App\Http\Controllers\Api\V1\SalesmanController::class, 'regions']);
        Route::post('salesmen/{salesman}/regions', [App\Http\Controllers\Api\V1\SalesmanController::class, 'assignRegions']);
        Route::delete('salesmen/{salesman}/regions/{regionId}', [App\Http\Controllers\Api\V1\SalesmanController::class, 'removeRegion']);
        Route::get('salesmen/{salesman}/clients', [App\Http\Controllers\Api\V1\SalesmanController::class, 'clients']);
        Route::post('salesmen/{salesman}/clients', [App\Http\Controllers\Api\V1\SalesmanController::class, 'assignClient']);
        Route::delete('salesmen/{salesman}/clients/{clientId}', [App\Http\Controllers\Api\V1\SalesmanController::class, 'removeClient']);
        Route::get('salesmen/{salesman}/orders', [App\Http\Controllers\Api\V1\SalesmanController::class, 'orders']);
        Route::post('salesmen/{salesman}/place-order', [App\Http\Controllers\Api\V1\SalesmanController::class, 'placeOrder']);
    });
});
