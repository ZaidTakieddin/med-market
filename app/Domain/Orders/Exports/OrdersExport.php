<?php

namespace App\Domain\Orders\Exports;

use App\Models\Order;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class OrdersExport implements FromArray, ShouldAutoSize, WithHeadings
{
    /**
     * @param  Collection<int, Order>  $orders
     */
    public function __construct(
        private Collection $orders
    ) {}

    /**
     * @return array<int, array<int, string|int>>
     */
    public function array(): array
    {
        $rows = [];
        $headings = [
            'Order Number',
            'Buyer Company',
            'Supplier Company',
            'Status',
            'Subtotal',
            'Tax',
            'Total',
            'Items Count',
            'Created At',
            'Created By',
        ];

        foreach ($this->orders as $order) {
            $rows[] = [
                $order->order_number,
                $order->buyerCompany->name ?? 'N/A',
                $order->supplierCompany->name ?? 'N/A',
                $order->status,
                number_format((float) $order->subtotal, 2),
                number_format((float) $order->tax_amount, 2),
                number_format((float) $order->total_amount, 2),
                $order->items->count(),
                optional($order->created_at)->format('Y-m-d H:i:s'),
                $order->creator->name ?? 'N/A',
            ];
        }

        return [$headings, ...$rows];
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            'Order Number',
            'Buyer Company',
            'Supplier Company',
            'Status',
            'Subtotal',
            'Tax',
            'Total',
            'Items Count',
            'Created At',
            'Created By',
        ];
    }
}
