<?php

namespace App\Domain\Pricing;

use App\Constants\PricingType;

class PriceResult
{
    public function __construct(
        public readonly float $unitPrice,
        public readonly float $originalPrice,
        public readonly string $pricingType,
        public readonly float $discountAmount,
        public readonly bool $wholesaleEligible,
        public readonly bool $minQtyMet,
        public readonly ?string $customPriceNote = null,
    ) {}

    public function isWholesale(): bool
    {
        return $this->pricingType === PricingType::WHOLESALE;
    }

    public function isRetail(): bool
    {
        return $this->pricingType === PricingType::RETAIL;
    }

    public function isCustom(): bool
    {
        return $this->pricingType === PricingType::CUSTOM;
    }

    public function hasDiscount(): bool
    {
        return $this->discountAmount > 0;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'unit_price' => round($this->unitPrice, 2),
            'original_price' => round($this->originalPrice, 2),
            'pricing_type' => $this->pricingType,
            'discount_amount' => round($this->discountAmount, 2),
            'wholesale_eligible' => $this->wholesaleEligible,
            'min_qty_met' => $this->minQtyMet,
            'custom_price_note' => $this->customPriceNote,
        ];
    }
}
