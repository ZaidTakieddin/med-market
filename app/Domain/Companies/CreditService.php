<?php

namespace App\Domain\Companies;

use App\Exceptions\DomainException;
use App\Models\BuyerSupplierRelationship;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Buyer<->supplier trade credit (B2B net terms). This is the credit a supplier
 * extends to a buyer — NOT platform monetization (see future wallet/billing).
 */
class CreditService
{
    /**
     * Assert (without reserving) that a net-term order of the given amount is allowed:
     * an approved relationship exists and the amount fits within the credit limit.
     * Used to fail fast before an order is created.
     *
     * @throws DomainException If there is no approved relationship or the limit would be exceeded
     */
    public function assertAvailable(int $buyerCompanyId, int $supplierCompanyId, float $amount): void
    {
        $relationship = BuyerSupplierRelationship::query()
            ->where('buyer_company_id', $buyerCompanyId)
            ->where('supplier_company_id', $supplierCompanyId)
            ->where('is_approved', true)
            ->first();

        if ($relationship === null) {
            throw new DomainException(
                'No approved credit relationship with this supplier. Use cash payment or request credit approval first.'
            );
        }

        if (! $relationship->hasCreditAvailable($amount)) {
            $available = max(0, (float) $relationship->credit_limit - (float) $relationship->current_balance);
            throw new DomainException(
                "Order exceeds available credit. Available: {$available}, required: {$amount}."
            );
        }
    }

    /**
     * Reserve credit for a net-term order: require an approved relationship, verify
     * the order fits within the credit limit, then add the amount to current_balance.
     * The row is locked so concurrent orders cannot both over-reserve.
     *
     * @throws DomainException If there is no approved relationship or the limit would be exceeded
     */
    public function reserve(int $buyerCompanyId, int $supplierCompanyId, float $amount): BuyerSupplierRelationship
    {
        $relationship = BuyerSupplierRelationship::query()
            ->where('buyer_company_id', $buyerCompanyId)
            ->where('supplier_company_id', $supplierCompanyId)
            ->where('is_approved', true)
            ->lockForUpdate()
            ->first();

        if ($relationship === null) {
            throw new DomainException(
                'No approved credit relationship with this supplier. Use cash payment or request credit approval first.'
            );
        }

        if (! $relationship->hasCreditAvailable($amount)) {
            $available = max(0, (float) $relationship->credit_limit - (float) $relationship->current_balance);
            throw new DomainException(
                "Order exceeds available credit. Available: {$available}, required: {$amount}."
            );
        }

        $relationship->current_balance = (float) $relationship->current_balance + $amount;
        $relationship->save();

        return $relationship;
    }

    /**
     * Release previously-reserved credit (on order cancellation or invoice payment).
     * Floors the balance at zero. No-op when no relationship exists.
     */
    public function release(int $buyerCompanyId, int $supplierCompanyId, float $amount): void
    {
        $relationship = BuyerSupplierRelationship::query()
            ->where('buyer_company_id', $buyerCompanyId)
            ->where('supplier_company_id', $supplierCompanyId)
            ->lockForUpdate()
            ->first();

        if ($relationship === null) {
            return;
        }

        $relationship->current_balance = max(0, (float) $relationship->current_balance - $amount);
        $relationship->save();
    }

    /**
     * Apply a manual adjustment to a relationship's outstanding balance. The row is
     * locked inside a transaction so concurrent adjustments cannot lose updates, and
     * the adjustment is rejected when it would push the balance below zero or past
     * the credit limit.
     *
     * @param  float  $amount  Positive to increase the outstanding balance, negative to decrease
     * @return array{relationship: BuyerSupplierRelationship, previousBalance: float}
     *
     * @throws DomainException If the result would go below zero or exceed the credit limit
     */
    public function adjustBalance(BuyerSupplierRelationship $relationship, float $amount, string $reason, int $adjustedBy): array
    {
        return DB::transaction(function () use ($relationship, $amount, $reason, $adjustedBy) {
            $relationship = $relationship->newQuery()
                ->whereKey($relationship->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $previousBalance = (float) $relationship->current_balance;
            $newBalance = $previousBalance + $amount;

            if ($newBalance < 0) {
                throw new DomainException(
                    "Balance cannot go below zero. Current balance: {$previousBalance}, requested adjustment: {$amount}."
                );
            }

            if ($relationship->credit_limit !== null && $newBalance > (float) $relationship->credit_limit) {
                throw new DomainException(
                    "Adjustment would exceed credit limit. Credit limit: {$relationship->credit_limit}, current balance: {$previousBalance}, requested adjustment: {$amount}."
                );
            }

            $relationship->current_balance = $newBalance;
            $relationship->save();

            \App\Support\AuditLogger::record(
                User::find($adjustedBy),
                'credit.balance.adjusted',
                $relationship,
                ['previous_balance' => $previousBalance, 'adjustment' => $amount, 'new_balance' => $newBalance, 'reason' => $reason],
            );

            Log::info('Credit balance adjusted', [
                'relationship_id' => $relationship->id,
                'buyer_company_id' => $relationship->buyer_company_id,
                'supplier_company_id' => $relationship->supplier_company_id,
                'previous_balance' => $previousBalance,
                'adjustment' => $amount,
                'new_balance' => $newBalance,
                'reason' => $reason,
                'adjusted_by' => $adjustedBy,
            ]);

            return ['relationship' => $relationship, 'previousBalance' => $previousBalance];
        });
    }
}
