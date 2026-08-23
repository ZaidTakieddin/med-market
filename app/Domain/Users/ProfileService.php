<?php

namespace App\Domain\Users;

use App\Exceptions\DomainException;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class ProfileService
{
    /**
     * Update the user's own profile fields.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateProfile(User $user, array $data): User
    {
        $user->update($data);

        return $user->refresh();
    }

    /**
     * Change the user's password after verifying the current one.
     *
     * @throws DomainException When the current password does not match
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw new DomainException('Current password is incorrect.');
        }

        $user->update([
            'password' => Hash::make($newPassword),
        ]);
    }

    /**
     * Update the user's company information.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws DomainException When the user has no company
     */
    public function updateCompany(User $user, array $data): Company
    {
        $company = $user->company;

        if ($company === null) {
            throw new DomainException('User does not belong to a company.');
        }

        $company->update($data);

        return $company->refresh();
    }
}
