<?php

namespace App\Providers;

use App\Models\BuyerSupplierRelationship;
use App\Models\City;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Offer;
use App\Models\Order;
use App\Models\OrderExport;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\Region;
use App\Models\StockMovement;
use App\Models\User;
use App\Observers\OrderObserver;
use App\Observers\ProductObserver;
use App\Observers\StockMovementObserver;
use App\Policies\BuyerSupplierRelationshipPolicy;
use App\Policies\CityPolicy;
use App\Policies\CompanyPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\OfferPolicy;
use App\Policies\OrderExportPolicy;
use App\Policies\OrderPolicy;
use App\Policies\PriceListPolicy;
use App\Policies\ProductPolicy;
use App\Policies\RegionPolicy;
use App\Policies\UserPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        User::class => UserPolicy::class,
        Company::class => CompanyPolicy::class,
        Product::class => ProductPolicy::class,
        Order::class => OrderPolicy::class,
        Invoice::class => InvoicePolicy::class,
        Offer::class => OfferPolicy::class,
        OrderExport::class => OrderExportPolicy::class,
        BuyerSupplierRelationship::class => BuyerSupplierRelationshipPolicy::class,
        PriceList::class => PriceListPolicy::class,
        City::class => CityPolicy::class,
        Region::class => RegionPolicy::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        // Register observers
        Order::observe(OrderObserver::class);
        Product::observe(ProductObserver::class);
        StockMovement::observe(StockMovementObserver::class);

        $this->registerRateLimiters();
    }

    /**
     * Define the application's rate limiters.
     */
    private function registerRateLimiters(): void
    {
        // Baseline protection for every authenticated API endpoint (on top of the
        // stricter auth limiter): 120 requests per minute per user account.
        RateLimiter::for('api', function (Request $request): array {
            return [
                Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()),
            ];
        });
    }
}
