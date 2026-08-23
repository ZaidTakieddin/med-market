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
}
