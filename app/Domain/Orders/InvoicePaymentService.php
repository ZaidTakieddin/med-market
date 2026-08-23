<?php

namespace App\Domain\Orders;

use App\Constants\PaymentTerms;
use App\Domain\Companies\CreditService;
use App\Exceptions\DomainException;
use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * Marks invoices as paid and releases the matching reserved trade credit as one
 * atomic unit — an invoice can never be paid while its credit reservation stays
 * open (or vice versa).
 */
class InvoicePaymentService
{
    public function __construct(
        private CreditService $creditService
    ) {}

    /**
     * Mark an invoice as paid (fully by default, or by the given payment amount)
     * inside a transaction with the invoice row locked, releasing the newly-paid
     * portion of a net-term order's reserved credit in the same transaction.
     *
     * @param  float|null  $amount  Payment received now; null pays the remaining total
     *
     * @throws DomainException If the payment exceeds the invoice total
     */
    public function markAsPaid(Invoice $invoice, ?float $amount = null): Invoice
    {
        return DB::transaction(function () use ($invoice, $amount) {
            $invoice = $invoice->newQuery()
                ->whereKey($invoice->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $paymentAmount = (float) ($amount ?? (float) $invoice->total_amount);

            if ($paymentAmount > (float) $invoice->total_amount) {
                throw new DomainException(
                    "Payment amount cannot exceed the invoice total. Total: {$invoice->total_amount}, received: {$paymentAmount}."
                );
            }

            $previouslyPaid = (float) $invoice->paid_amount;
            $newlyPaid = min(max(0, $paymentAmount), max(0, (float) $invoice->total_amount - $previouslyPaid));

            // Nothing new to settle (e.g. a repeated full payment): no-op, keeps the
            // endpoint idempotent so credit is never released twice.
            if ($newlyPaid > 0) {
                $order = Order::find($invoice->order_id);
                $isCreditSale = $order !== null && $order->payment_method !== PaymentTerms::CASH;

                if ($isCreditSale) {
                    $this->creditService->release(
                        (int) $invoice->buyer_company_id,
                        (int) $invoice->supplier_company_id,
                        $newlyPaid
                    );
                }

                $invoice->markAsPaid($newlyPaid);
            }

            return $invoice;
        });
    }
}
