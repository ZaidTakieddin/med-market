<?php

namespace App\Domain\Companies;

use App\Exceptions\DomainException;
use App\Models\BuyerSupplierRelationship;

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
}
