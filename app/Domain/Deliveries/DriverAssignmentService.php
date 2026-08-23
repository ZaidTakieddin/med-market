<?php

namespace App\Domain\Deliveries;

use App\Exceptions\DomainException;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DriverAssignmentService
{
    /**
     * Assign a driver to an order. The driver must be a user with the driver role
     * belonging to the order's supplying company (admins may assign any driver).
     * Serving the pharmacy's region is recommended but not enforced — a mismatch is
     * logged as a warning.
     *
     * @throws DomainException When the target user is not a driver or belongs to another company
     */
    public function assign(Order $order, User $driver, User $assignedBy): Order
    {
        if (! $driver->isDriver()) {
            throw new DomainException('The selected user is not a driver.');
        }

        if (! $assignedBy->isAdmin() && $driver->company_id !== $order->supplier_company_id) {
            throw new DomainException('Driver must belong to the supplier company of the order.');
        }

        return DB::transaction(function () use ($order, $driver, $assignedBy): Order {
            // Non-blocking recommendation: warn when the driver does not cover the
            // pharmacy's region.
            $buyerCompany = $order->buyerCompany;
            if ($buyerCompany !== null && $buyerCompany->region_id !== null && ! $driver->servesRegion($buyerCompany->region_id)) {
                Log::warning('Driver assigned to order outside their region', [
                    'driver_id' => $driver->id,
                    'order_id' => $order->id,
                    'pharmacy_region_id' => $buyerCompany->region_id,
                ]);
            }

            $order->driver()->associate($driver);
            $order->save();

            Log::info('Driver assigned to order', [
                'order_id' => $order->id,
                'driver_id' => $driver->id,
                'supplier_company_id' => $order->supplier_company_id,
                'buyer_company_id' => $order->buyer_company_id,
                'assigned_by' => $assignedBy->id,
            ]);

            return $order;
        });
    }
}
