<?php

namespace App\Providers;

use App\Models\BuyerSupplierRelationship;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Observers\OrderObserver;
use App\Observers\ProductObserver;
use App\Observers\StockMovementObserver;
use App\Policies\BuyerSupplierRelationshipPolicy;
use App\Policies\CompanyPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\OrderPolicy;
use App\Policies\PriceListPolicy;
use App\Policies\ProductPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

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
        BuyerSupplierRelationship::class => BuyerSupplierRelationshipPolicy::class,
        PriceList::class => PriceListPolicy::class,
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
    }
}
