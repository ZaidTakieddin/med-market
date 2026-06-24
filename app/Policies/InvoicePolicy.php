<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    /**
     * Only the issuing supplier (or admin) may confirm payment. A buyer must not be
     * able to mark their own invoice paid — that would release their trade credit
     * without actually paying.
     */
    public function pay(User $user, Invoice $invoice): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isSupplier() && $invoice->supplier_company_id === $user->company_id;
    }

    /**
     * Only the issuing supplier (or admin) marks an invoice as sent.
     */
    public function send(User $user, Invoice $invoice): bool
    {
        return $this->pay($user, $invoice);
    }
}
