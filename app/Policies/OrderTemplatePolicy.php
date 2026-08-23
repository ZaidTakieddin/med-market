<?php

namespace App\Policies;

use App\Models\OrderTemplate;
use App\Models\User;

class OrderTemplatePolicy
{
    /**
     * Buyers and salesmen of a buyer company can keep templates; templates are
     * shared across the whole company.
     */
    public function viewAny(User $user): bool
    {
        return $user->canBuy();
    }

    public function view(User $user, OrderTemplate $template): bool
    {
        return $user->canBuy() && $user->company_id === $template->buyer_company_id;
    }

    public function create(User $user): bool
    {
        return $user->canBuy()
            && $user->company !== null
            && $user->company->isBuyer();
    }

    public function update(User $user, OrderTemplate $template): bool
    {
        return $this->view($user, $template);
    }

    public function delete(User $user, OrderTemplate $template): bool
    {
        return $this->view($user, $template);
    }

    public function reorder(User $user, OrderTemplate $template): bool
    {
        return $this->view($user, $template);
    }
}
