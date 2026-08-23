<?php

namespace App\Domain\Users;

use App\Constants\UserRole;
use App\Exceptions\DomainException;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserManagementService
{
    /**
     * Create a user with any role (admin capability).
     *
     * @param  array<string, mixed>  $data  Validated attributes including password
     *
     * @throws DomainException When a company-requiring role has no company_id
     */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            /** @var array<string, mixed> $attributes */
            $attributes = [
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make($data['password']),
                'role' => $data['role'],
                'company_id' => $data['company_id'] ?? null,
            ];

            return User::create($attributes);
        });
    }

    /**
     * Update profile fields, optional password reset, and role/company assignment.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws DomainException When demoting the last active admin
     */
    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $demotingAdmin = isset($data['role'])
                && $user->role === UserRole::ADMIN
                && $data['role'] !== UserRole::ADMIN;

            if ($demotingAdmin && $this->activeAdminCountExcluding($user) === 0) {
                throw new DomainException('Cannot demote the last remaining admin.');
            }

            if (isset($data['password']) && $data['password'] !== '') {
                $data['password'] = Hash::make((string) $data['password']);
            } else {
                unset($data['password']);
            }

            $user->update($data);

            return $user->refresh();
        });
    }

    /**
     * Soft-delete a user account.
     *
     * @throws DomainException When deleting yourself or the last active admin
     */
    public function delete(User $actor, User $user): void
    {
        if ($actor->id === $user->id) {
            throw new DomainException('You cannot delete your own account.');
        }

        if ($user->isAdmin() && $this->activeAdminCountExcluding($user) === 0) {
            throw new DomainException('Cannot delete the last remaining admin.');
        }

        $user->delete();
    }

    private function activeAdminCountExcluding(User $except): int
    {
        return (int) User::query()
            ->where('role', UserRole::ADMIN)
            ->whereKeyNot($except->getKey())
            ->count();
    }
}
