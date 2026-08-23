<?php

namespace App\Domain\Deliveries;

use App\Constants\DriverStatus;
use App\Constants\UserRole;
use App\Exceptions\DomainException;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DriverService
{
    /**
     * Create a driver account under the creator's company.
     *
     * @param  array<string, mixed>  $data  Validated driver attributes (name, email, phone, password, work/employment fields)
     *
     * @throws DomainException When the creator has no company to attach the driver to
     */
    public function create(User $creator, array $data): User
    {
        $companyId = $creator->company_id;
        if ($companyId === null) {
            throw new DomainException('A driver must belong to a supplier company.');
        }

        return DB::transaction(function () use ($data, $companyId): User {
            $driver = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => Hash::make($data['password']),
                'company_id' => $companyId,
                'role' => UserRole::DRIVER,
                'work_start_time' => $data['work_start_time'] ?? null,
                'work_end_time' => $data['work_end_time'] ?? null,
                'employment_start_date' => $data['employment_start_date'] ?? null,
                'employment_end_date' => $data['employment_end_date'] ?? null,
                'driver_status' => $data['driver_status'] ?? DriverStatus::AVAILABLE,
            ]);

            return $driver->refresh();
        });
    }

    /**
     * Update a driver's profile/work fields. Passwords are handled via the
     * dedicated profile endpoints and can never be set through this path.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(User $driver, array $data): User
    {
        unset($data['password']);

        $driver->update($data);

        return $driver->refresh();
    }

    public function delete(User $driver): void
    {
        $driver->delete();
    }

    /**
     * Update a driver's availability status.
     *
     * @throws DomainException When the status is invalid
     */
    public function setStatus(User $driver, string $status): User
    {
        if (! DriverStatus::isValid($status)) {
            throw new DomainException("Invalid driver status: {$status}");
        }

        $driver->driver_status = $status;
        $driver->save();

        return $driver;
    }

    /**
     * Drivers who are available and below their concurrent-delivery capacity,
     * optionally filtered to those serving a specific region. Scoped to the
     * viewing supplier's company (admins see every company's drivers).
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    public function availableFor(?User $viewer, ?int $regionId = null): \Illuminate\Support\Collection
    {
        $query = User::query()
            ->where('role', UserRole::DRIVER)
            ->where('driver_status', DriverStatus::AVAILABLE)
            ->with('company')
            ->withCount(['driverOrders as active_assignments' => fn ($q) => $q->where('status', 'shipped')])
            ->orderBy('active_assignments');

        if ($viewer !== null && ! $viewer->isAdmin() && $viewer->company_id !== null) {
            $query->where('company_id', $viewer->company_id);
        }

        // Capacity check post-fetch: the count is a select alias.
        $drivers = $query->get()->filter(
            fn (User $driver): bool => (int) $driver->getAttribute('active_assignments') < (int) $driver->max_concurrent_orders
        )->values();

        if ($regionId !== null) {
            $drivers = $drivers->filter(fn (User $driver): bool => $driver->servesRegion($regionId));
        }

        return $drivers->values();
    }

    /**
     * Suggest the best driver for an order: available, under capacity, covering the
     * buyer's region when known — least active assignments first.
     */
    public function suggestFor(\App\Models\Order $order, ?User $viewer): ?User
    {
        $buyerCompany = $order->buyerCompany;

        $candidates = $this->availableFor($viewer, $buyerCompany?->region_id)
            ->filter(fn (User $driver): bool => $driver->company_id === $order->supplier_company_id);

        /** @var User|null */
        return $candidates->sortBy(fn (User $driver): int => (int) $driver->getAttribute('active_assignments'))->first();
    }
}
