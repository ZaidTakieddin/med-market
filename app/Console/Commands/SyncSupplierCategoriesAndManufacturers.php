<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Product;
use Illuminate\Console\Command;

class SyncSupplierCategoriesAndManufacturers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'suppliers:sync-categories-manufacturers 
                            {--supplier-id= : Sync specific supplier by ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync supplier categories and manufacturers from their products (auto-sync)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $supplierId = $this->option('supplier-id');

        if ($supplierId) {
            $supplier = Company::where('type', 'supplier')->find($supplierId);

            if (!$supplier) {
                $this->error("Supplier with ID {$supplierId} not found.");
                return Command::FAILURE;
            }

            $this->syncSupplier($supplier);
            $this->info("Synced categories and manufacturers for supplier: {$supplier->name}");
        } else {
            // Sync all suppliers
            $suppliers = Company::where('type', 'supplier')->get();
            $this->info("Syncing categories and manufacturers for {$suppliers->count()} suppliers...");

            $bar = $this->output->createProgressBar($suppliers->count());
            $bar->start();

            foreach ($suppliers as $supplier) {
                $this->syncSupplier($supplier);
                $bar->advance();
            }

            $bar->finish();
            $this->newLine();
            $this->info("Successfully synced all suppliers!");
        }

        return Command::SUCCESS;
    }

    /**
     * Sync categories and manufacturers for a specific supplier.
     */
    protected function syncSupplier(Company $supplier): void
    {
        // Get unique category IDs from supplier's active products
        $categoryIds = Product::where('supplier_company_id', $supplier->id)
            ->where('is_active', true)
            ->whereNotNull('category_id')
            ->distinct()
            ->pluck('category_id')
            ->filter()
            ->toArray();

        // Get unique manufacturer IDs from supplier's active products
        $manufacturerIds = Product::where('supplier_company_id', $supplier->id)
            ->where('is_active', true)
            ->whereNotNull('manufacturer_id')
            ->distinct()
            ->pluck('manufacturer_id')
            ->filter()
            ->toArray();

        // Sync categories
        $supplier->categories()->sync($categoryIds);

        // Sync manufacturers
        $supplier->manufacturers()->sync($manufacturerIds);
    }
}
