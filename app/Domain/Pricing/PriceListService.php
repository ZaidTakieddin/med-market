<?php

namespace App\Domain\Pricing;

use App\Models\PriceList;
use Illuminate\Support\Facades\DB;

class PriceListService
{
    /**
     * Create a custom price entry. Duplicate protection (supplier + buyer + product
     * + min_qty) is additionally enforced at the DB level by a partial unique index.
     *
     * @param  array<string, mixed>  $data  Validated attributes including supplier_company_id
     */
    public function create(array $data): PriceList
    {
        return DB::transaction(function () use ($data): PriceList {
            /** @var PriceList */
            return PriceList::create($data);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(PriceList $entry, array $data): PriceList
    {
        $entry->update($data);

        return $entry->refresh();
    }

    public function delete(PriceList $entry): void
    {
        $entry->delete();
    }
}
