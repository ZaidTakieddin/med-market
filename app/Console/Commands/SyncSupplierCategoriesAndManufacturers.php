<?php

namespace App\Console\Commands;

use App\Constants\CompanyType;
use App\Domain\Companies\SupplierTaxonomySyncService;
use App\Models\Company;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Console\Command;

class SyncSupplierCategoriesAndManufacturers extends Command
{
    public function __construct(private SupplierTaxonomySyncService $taxonomySync)
    {
        parent::__construct();
    }

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'suppliers:sync-categories-manufacturers 
                            {--supplier-id= : Sync specific supplier by ID}
                            {--buyer-id= : Sync specific buyer by ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync company categories and manufacturers (suppliers from products, buyers from orders)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $supplierId = $this->option('supplier-id');
        $buyerId = $this->option('buyer-id');

        if ($supplierId) {
            $supplier = Company::where('type', CompanyType::SUPPLIER)->find($supplierId);
            if (! $supplier) {
                $this->error("Supplier with ID {$supplierId} not found.");

                return Command::FAILURE;
            }
            $this->syncSupplier($supplier);
            $this->info("Synced categories and manufacturers for supplier: {$supplier->name}");

            return Command::SUCCESS;
        }

        if ($buyerId) {
            $buyer = Company::where('type', CompanyType::BUYER)->find($buyerId);
            if (! $buyer) {
                $this->error("Buyer with ID {$buyerId} not found.");

                return Command::FAILURE;
            }
            $this->syncBuyer($buyer);
            $this->info("Synced categories and manufacturers for buyer: {$buyer->name}");

            return Command::SUCCESS;
        }

        // Sync all suppliers and buyers
        $suppliers = Company::where('type', CompanyType::SUPPLIER)->get();
        $buyers = Company::where('type', CompanyType::BUYER)->get();
        $total = $suppliers->count() + $buyers->count();

        $this->info("Syncing categories and manufacturers for {$total} companies...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($suppliers as $supplier) {
            $this->syncSupplier($supplier);
            $bar->advance();
        }
        foreach ($buyers as $buyer) {
            $this->syncBuyer($buyer);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Successfully synced all companies!');

        return Command::SUCCESS;
    }

    /**
     * Sync categories and manufacturers for a supplier from their products.
     */
    protected function syncSupplier(Company $supplier): void
    {
        $this->taxonomySync->syncForCompany($supplier->id);
    }

    /**
     * Sync categories and manufacturers for a buyer from products in their orders.
     */
    protected function syncBuyer(Company $buyer): void
    {
        $productIds = OrderItem::whereHas('order', fn ($q) => $q->where('buyer_company_id', $buyer->id))
            ->distinct()
            ->pluck('product_id')
            ->filter()
            ->toArray();

        if (empty($productIds)) {
            $buyer->categories()->sync([]);
            $buyer->manufacturers()->sync([]);

            return;
        }

        $categoryIds = Product::whereIn('id', $productIds)
            ->whereNotNull('category_id')
            ->distinct()
            ->pluck('category_id')
            ->filter()
            ->toArray();

        $manufacturerIds = Product::whereIn('id', $productIds)
            ->whereNotNull('manufacturer_id')
            ->distinct()
            ->pluck('manufacturer_id')
            ->filter()
            ->toArray();

        $buyer->categories()->sync($categoryIds);
        $buyer->manufacturers()->sync($manufacturerIds);
    }
}
