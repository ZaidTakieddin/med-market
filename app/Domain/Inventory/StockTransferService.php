<?php

namespace App\Domain\Inventory;

use App\Constants\StockMovementType;
use App\Exceptions\DomainException;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StockTransferService
{
    public function __construct(
        private StockMovementService $stockMovementService
    ) {}

    /**
     * Move stock between two products belonging to different supplier companies in a
     * single transaction: an `out` movement on the source and an `in` movement on
     * the destination, cross-referenced via reference_type = "transfer". Both product
     * rows are locked up-front in id order to avoid deadlocks under concurrency.
     *
     * @throws DomainException When source/destination are invalid or stock is insufficient
     */
    public function transfer(Product $from, Product $to, int $quantity, User $actor, ?string $reason = null): void
    {
        if ($quantity <= 0) {
            throw new DomainException('Transfer quantity must be at least 1.');
        }

        if ((int) $from->getKey() === (int) $to->getKey()) {
            throw new DomainException('Source and destination products must be different.');
        }

        if ($from->supplier_company_id === $to->supplier_company_id) {
            throw new DomainException('Transfers must be between different supplier companies.');
        }

        DB::transaction(function () use ($from, $to, $quantity, $actor, $reason): void {
            // Lock both rows in consistent (ascending id) order.
            Product::query()
                ->whereIn('id', [(int) $from->getKey(), (int) $to->getKey()])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            /** @var Product $lockedFrom */
            $lockedFrom = Product::query()->findOrFail((int) $from->getKey());
            /** @var Product $lockedTo */
            $lockedTo = Product::query()->findOrFail((int) $to->getKey());

            if ((int) $lockedFrom->stock_quantity < $quantity) {
                throw new DomainException(
                    "Insufficient stock on {$lockedFrom->sku}. Available: {$lockedFrom->stock_quantity}, requested: {$quantity}."
                );
            }

            $reason ??= 'Inter-supplier transfer';

            $this->stockMovementService->recordMovement(
                product: $lockedFrom,
                type: StockMovementType::OUT,
                quantity: -$quantity,
                userId: $actor->id,
                reason: $reason,
                referenceType: 'transfer',
                referenceId: (string) $lockedTo->getKey(),
            );

            // Carry the source's current batch/expiry so traceability survives the hop.
            $this->stockMovementService->recordMovement(
                product: $lockedTo,
                type: StockMovementType::IN,
                quantity: $quantity,
                userId: $actor->id,
                reason: $reason.' (from '.$lockedFrom->sku.')',
                referenceType: 'transfer',
                referenceId: (string) $lockedFrom->getKey(),
                batchNumber: $lockedFrom->batch_number,
                expiryDate: $lockedFrom->expiry_date?->toDateString(),
            );
        });
    }
}
