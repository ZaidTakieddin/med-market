<?php

namespace App\Domain\Products\Import;

use App\Domain\Products\ProductImportService;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * A ready-to-edit sample sheet. Headings are derived from the importer's
 * canonical column list so the template can never drift from what is parsed.
 * Replace the example rows with your own products before importing.
 */
class ProductsSampleExport implements FromArray, WithHeadings
{
    /**
     * @return list<list<mixed>>
     */
    public function array(): array
    {
        return [
            // Full row: every column populated.
            ['Paracetamol 500mg', 'PAR-500-001', 25.50, 'Pain reliever and fever reducer', 'مسكنات', 'شركة الأدوية الوطنية', '1234567890123', 'box', 18.00, 100, 30.00, 10, 'percentage', 500, 50, 'true'],
            // Wholesale row: wholesale_price requires wholesale_min_qty.
            ['Amoxicillin 250mg', 'AMOX-250-002', 40.00, '', 'مضادات حيوية', '', '', 'box', 32.00, 50, '', '', '', 200, 20, 'true'],
            // Discounted row: fixed discount, no wholesale tier.
            ['Vitamin C 1000mg', 'VITC-1000-003', 15.00, '', 'فيتامينات', '', '', 'bottle', '', '', 20.00, 5, 'fixed', 300, 30, 'true'],
            // Minimal row: only the three required columns (name, sku, pharmacist_price).
            ['Ibuprofen 400mg', 'IBU-400-004', 22.00, '', '', '', '', '', '', '', '', '', '', '', '', ''],
        ];
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return ProductImportService::COLUMNS;
    }
}
