<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Private/presence channel authorization for WebSockets (Reverb). Company
| channels keep every realtime feed tenant-scoped.
|
*/

// Supplier-side company feed: order events, stock levels, driver locations.
Broadcast::channel('supplier.{companyId}', function (User $user, int $companyId) {
    if ($user->isAdmin()) {
        return true;
    }

    return ($user->isSupplier() || $user->isSalesman())
        && (int) $user->company_id === $companyId;
});

// Buyer-side company feed: order status updates.
Broadcast::channel('buyer.{companyId}', function (User $user, int $companyId) {
    if ($user->isAdmin()) {
        return true;
    }

    return $user->canBuy() && (int) $user->company_id === $companyId;
});

// A driver's own assignment feed.
Broadcast::channel('driver.{driverId}', function (User $user, int $driverId) {
    if ($user->isAdmin()) {
        return true;
    }

    return $user->isDriver() && (int) $user->id === $driverId;
});

// Presence: which of the company's users are online right now.
Broadcast::channel('presence-company.{companyId}', function (User $user, int $companyId) {
    if ((int) $user->company_id !== $companyId) {
        return false;
    }

    return ['id' => $user->id, 'name' => $user->name];
});
