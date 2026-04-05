# Pricing Domain

This domain handles the multi-tier pricing logic in the Med Market B2B system. It centralizes price resolution based on buyer type, supplier classification, quantity, and custom price lists.

## Overview

The Pricing domain manages how prices are determined for different buyer-supplier combinations. It supports three pricing tiers (wholesale, retail, custom) and integrates with the existing offer/promotion system.

## Key Concepts

### Three-Tier Pricing Model

Products support three price points:

| Price | Field | Who Pays | Notes |
|-------|-------|----------|-------|
| **Wholesale** | `wholesale_price` | Salesmen, bulk buyers | Only when `qty >= wholesale_min_qty` |
| **Retail** | `pharmacist_price` | Pharmacists (retail buyers) | Standard B2B price |
| **Customer** | `customer_price` | End consumer | Suggested retail price (informational) |

### Price Resolution Order

When determining the price for a product, the system follows this priority:

1. **Custom Price List** — Check `price_lists` for a negotiated price for this specific buyer + product
2. **Wholesale Tier** — If buyer is wholesale-eligible AND quantity >= `wholesale_min_qty`, use `wholesale_price`
3. **Retail Fallback** — Use `pharmacist_price` (standard B2B price)
4. **Product Discount** — Apply product-level discount (`discount` + `discount_type`)
5. **Promotional Offers** — Apply offers on top (handled by `CartOfferService`)

### Custom Price Lists

Suppliers can set negotiated prices per buyer for special customers:

- Stored in `price_lists` table
- Can have date ranges (`valid_from`, `valid_to`)
- Can specify minimum quantities (`min_qty`)
- Highest priority in price resolution
- Managed by supplier, visible to specific buyer only

### Pricing Type Tracking

Every cart item and order item records which pricing tier was applied:
- `wholesale` — Wholesale price was used
- `retail` — Retail/pharmacist price was used
- `custom` — Custom price list was used

This is stored in `cart_items.pricing_type` and `order_items.pricing_type` for audit and transparency.

## Supplier-Buyer Pricing Matrix

| Supplier Classification | Buyer: Pharmacy | Buyer: Salesman |
|------------------------|-----------------|-----------------|
| **Wholesale** | Hidden from catalog | Wholesale price (if qty met), else retail |
| **Retail** | Retail price | Retail price (no wholesale available) |
| **Hybrid** | Retail price | Wholesale price (if qty met), else retail |

### Key Rules

- Wholesale min qty is enforced **per line item**, not per order
- If a salesman doesn't meet the wholesale min qty, they get the **retail price** (not rejected)
- Wholesale-only suppliers are **hidden** from pharmacist buyers in the catalog
- Custom price lists override all tier-based pricing when present and valid

## Related Constants

- `App\Constants\PricingType` — `WHOLESALE`, `RETAIL`, `CUSTOM`
- `App\Constants\SupplierClassification` — `WHOLESALE`, `RETAIL`, `HYBRID`
- `App\Constants\BuyerClassification` — `PHARMACY`, `SALESMAN`
- `App\Constants\PaymentTerms` — `CASH`, `NET_15`, `NET_30`, `NET_60`

## Related Models

- `App\Models\PriceList` — Custom per-buyer pricing entries
- `App\Models\Product` — Wholesale pricing fields (`wholesale_price`, `wholesale_min_qty`)
- `App\Models\Company` — Supplier/buyer classification fields
- `App\Models\CartItem` — `pricing_type` field
- `App\Models\OrderItem` — `pricing_type` field

## Domain Services

### PricingService (Implemented)

**Location**: `App\Domain\Pricing\PricingService`

**Purpose**: Central service that resolves the correct price for any product given the buyer context and quantity.

**Key Methods**:
- `resolve(Product, Company $buyer, int $qty)` — Returns a `PriceResult` with the resolved price, tier, discount info
- `resolveMany(array $items, Company $buyer)` — Batch resolution with preloaded custom price lists for performance
- `isWholesaleEligible(Company $supplier, Company $buyer)` — Check wholesale eligibility based on classifications
- `canBuyerSeeSupplier(Company $supplier, Company $buyer)` — Catalog visibility (wholesale-only hidden from pharmacies)
- `getDisplayPrice(Product, Company $buyer, int $qty)` — Returns a formatted array for API display with pricing breakdown

### PriceResult DTO (Implemented)

**Location**: `App\Domain\Pricing\PriceResult`

**Fields**: `unitPrice`, `originalPrice`, `pricingType`, `discountAmount`, `wholesaleEligible`, `minQtyMet`, `customPriceNote`

### PriceListController (Implemented — Phase 4)

**Location**: `App\Http\Controllers\Api\V1\PriceListController`

**Endpoints**: Full CRUD + `forBuyer/{buyerCompanyId}` for batch retrieval of valid prices.
Uses `PriceListPolicy` for authorization (suppliers manage, buyers view).

## Integration Points (All Implemented)

### With Products Domain
- Products carry wholesale pricing fields
- Product-level discounts apply after tier resolution
- `ProductController::index()` filters wholesale-only suppliers from pharmacy buyers

### With Cart Domain (Phase 3 — Implemented)
- `CartController` injects `PricingService` and uses `resolve()` on every add/update/remove
- Price recalculates when quantity crosses wholesale threshold (wholesale_min_qty)
- `pricing_type` is stored on cart items and passed through to order items on checkout
- `CartOfferService` accepts a `$basePrice` parameter — offer discounts apply on top of the tier-resolved price
- Catalog visibility check: `canBuyerSeeSupplier()` called before adding products to cart

### With Orders Domain (Phase 3 — Implemented)
- `OrderController::store()` uses `PricingService` when `unit_price` is not provided
- `OrderCreationService` accepts and stores `pricing_type` on order items
- `OrderItemResource` and `CartItemResource` expose `pricingType`

### With Offers Domain
- Promotional offers layer on top of tier-resolved prices
- `CartOfferService` receives the tier-resolved base price and calculates offer discounts from that base

## Business Rules

1. Custom price list has highest priority
2. Wholesale pricing requires: supplier is wholesale-capable + buyer is wholesale-eligible + qty >= min_qty
3. Per-item min qty enforcement (not per-order)
4. Salesman falls back to retail price if min qty not met (no rejection)
5. Product discount applies after tier selection
6. Promotional offers apply after discount

## Future Enhancements

- Volume-based price tiers (multiple break points per product)
- Loyalty-based dynamic pricing
- Seasonal pricing overrides
- Price history tracking and analytics
- Margin calculation tools for salesmen
