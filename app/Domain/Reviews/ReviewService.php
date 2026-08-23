<?php

namespace App\Domain\Reviews;

use App\Constants\OrderStatus;
use App\Exceptions\DomainException;
use App\Models\Company;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReviewService
{
    /**
     * Create (or replace) the user's review of a product or supplier. A verified
     * purchase is required: a delivered order of the user's buyer company that
     * contains the product, or was supplied by the company.
     *
     * @throws DomainException When there is no qualifying delivered order
     */
    public function create(User $user, string $reviewableType, int $reviewableId, int $rating, ?string $body, ?int $orderId = null): Review
    {
        if ($rating < 1 || $rating > 5) {
            throw new DomainException('Rating must be between 1 and 5.');
        }

        $reviewableClass = [Review::TYPE_PRODUCT, Review::TYPE_COMPANY][$reviewableType === Review::TYPE_COMPANY ? 1 : 0];
        $reviewable = $reviewableClass::query()->findOrFail($reviewableId);

        $order = $this->findVerifiedOrder($user, $reviewableClass, $reviewable, $orderId);

        return DB::transaction(function () use ($user, $reviewableClass, $reviewable, $rating, $body, $order): Review {
            /** @var Review|null */
            $existing = Review::query()
                ->where('user_id', $user->id)
                ->where('reviewable_type', $reviewableClass)
                ->where('reviewable_id', $reviewable->getKey())
                ->first();

            if ($existing !== null) {
                $existing->update(['rating' => $rating, 'body' => $body, 'order_id' => $order?->getKey()]);

                return $existing->refresh();
            }

            return Review::create([
                'user_id' => $user->id,
                'reviewable_type' => $reviewableClass,
                'reviewable_id' => $reviewable->getKey(),
                'order_id' => $order?->getKey(),
                'rating' => $rating,
                'body' => $body,
                'status' => Review::STATUS_APPROVED,
            ]);
        });
    }

    /**
     * Aggregates for a reviewable entity: average rating and approved count.
     *
     * @return array{average: float|null, count: int, distribution: array<int, int>}
     */
    public function aggregates(string $reviewableType, int $reviewableId): array
    {
        /** @var Collection<int, Review> $reviews */
        $reviews = Review::query()
            ->where('reviewable_type', $reviewableType)
            ->where('reviewable_id', $reviewableId)
            ->approved()
            ->get(['rating']);

        $distribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        foreach ($reviews as $review) {
            $distribution[$review->rating] = ($distribution[$review->rating] ?? 0) + 1;
        }

        /** @var float|int|null $average */
        $average = $reviews->avg('rating');

        return [
            'average' => $average !== null ? round((float) $average, 2) : null,
            'count' => $reviews->count(),
            'distribution' => $distribution,
        ];
    }

    /**
     * A delivered order of the user's buyer company matching the review target.
     */
    private function findVerifiedOrder(User $user, string $class, Product|Company $reviewable, ?int $orderId): ?Order
    {
        $companyId = $user->company_id;
        if ($companyId === null) {
            throw new DomainException('Only buyer-company users can write reviews.');
        }

        $orders = Order::query()
            ->where('buyer_company_id', $companyId)
            ->where('status', OrderStatus::DELIVERED)
            ->when($orderId !== null, fn ($q) => $q->whereKey($orderId))
            ->get();

        foreach ($orders as $order) {
            if ($class === Review::TYPE_COMPANY && (int) $order->supplier_company_id === (int) $reviewable->getKey()) {
                return $order;
            }

            if ($class === Review::TYPE_PRODUCT
                && Order::query()->whereKey($order->getKey())->whereHas('items', fn ($q) => $q->where('product_id', $reviewable->getKey()))->exists()) {
                return $order;
            }
        }

        throw new DomainException('A delivered order with this product/supplier is required to review.');
    }
}
