<?php

namespace App\Domain\Products;

use App\Models\Category;
use App\Models\Manufacturer;

class TaxonomyService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function createCategory(array $data): Category
    {
        return Category::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateCategory(Category $category, array $data): Category
    {
        $category->update($data);

        return $category->refresh();
    }

    public function deleteCategory(Category $category): void
    {
        $category->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createManufacturer(array $data): Manufacturer
    {
        return Manufacturer::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateManufacturer(Manufacturer $manufacturer, array $data): Manufacturer
    {
        $manufacturer->update($data);

        return $manufacturer->refresh();
    }

    public function deleteManufacturer(Manufacturer $manufacturer): void
    {
        $manufacturer->delete();
    }
}
