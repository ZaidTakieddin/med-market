# Products Domain

This domain handles product-related business logic in the Med Market B2B system. Products represent medical/pharmaceutical items that suppliers offer to buyers (pharmacies).

## Overview

The Products domain manages product information, pricing, inventory, and relationships. While product CRUD operations are handled in controllers, this domain is prepared for future product-specific business logic and services.

## Key Concepts

### Product Structure

Products contain:
- **Basic Information**: Name, description, SKU, barcode, unit
- **Categorization**: Category (foreign key to categories table, admin-managed), Manufacturer (foreign key to manufacturers table, admin-managed)
- **Pricing**: Pharmacist price, customer price, discount, discount type
- **Inventory**: Stock quantity, minimum stock level
- **Relationships**: Supplier company, category, manufacturer, order items, stock movements, offers

### Three-Tier Pricing System

Products support three pricing tiers:
- **Wholesale Price** (`wholesale_price`) - Lowest price, for salesmen/bulk buyers when `qty >= wholesale_min_qty` (optional)
- **Pharmacist Price** (`pharmacist_price`) - Standard B2B price for pharmacies/buyers (required)
- **Customer Price** (`customer_price`) - Suggested retail price for end customers (optional)

The `wholesale_min_qty` field defines the minimum quantity per line item to qualify for wholesale pricing.

### Discount System

Products can have discounts applied to the pharmacist price only:
- **Discount Type**: `percentage` or `fixed`
- **Discount Value**: Amount or percentage
- **pharmacistPriceAfterDiscount**: Returned in API when discount exists (calculated price after discount)
- Customer price is returned as-is; product discount does not apply to it

### Categories and Manufacturers

Products are organized by categories and manufacturers, both managed by admins:
- **Categories**: Admin-managed categories (e.g., "مسكنات", "مضادات حيوية", "فيتامينات")
  - Products reference categories via `category_id` foreign key
  - Categories cannot be created by suppliers (prevents conflicts)
  - All authenticated users can list categories for product creation
- **Manufacturers**: Admin-managed manufacturers (e.g., "شركة الأدوية الوطنية")
  - Products reference manufacturers via `manufacturer_id` foreign key
  - Manufacturers cannot be created by suppliers (prevents conflicts)
  - All authenticated users can list manufacturers for product creation

## Product Model Features

### Price Calculation Methods

```php
// Retail pricing
$price = $product->getEffectivePharmacistPrice();
$finalPrice = $product->getFinalPharmacistPrice();

// Wholesale pricing
$wholesalePrice = $product->getEffectiveWholesalePrice(); // null if not set
$finalWholesale = $product->getFinalWholesalePrice();      // null if not set

// Customer pricing
$price = $product->getEffectiveCustomerPrice();
$finalPrice = $product->getFinalCustomerPrice();

// Check wholesale eligibility
if ($product->qualifiesForWholesale($quantity)) {
    // Use wholesale price
}
```

### Stock Management

```php
// Check if product is low on stock
if ($product->isLowStock()) {
    // Trigger low stock alert
}
```

### Relationships

- `supplierCompany()` - Belongs to supplier company
- `category()` - Belongs to category (admin-managed)
- `manufacturer()` - Belongs to manufacturer (admin-managed)
- `orderItems()` - Has many order items
- `stockMovements()` - Has many stock movements
- `offers()` - Belongs to many offers (with pivot: quantity_required, quantity_free, discount_override)
- `priceLists()` - Has many custom price list entries

## Current Implementation

### Controllers

Product operations are handled in `ProductController`:
- List products with filtering (supplier_company_id, category_id, manufacturer_id, is_active) and search
- Create products (suppliers only)
- Get product details
- Update products
- Delete products (soft delete)

### Validation

Product validation is handled in:
- `CreateProductRequest` - For creating products
- `UpdateProductRequest` - For updating products

### Business Logic

Currently, product business logic is in:
- **Models**: Price calculation methods, stock checking
- **Controllers**: Filtering, search, authorization
- **Policies**: Authorization rules

## Future Domain Services

This domain is prepared for future services:

### ProductPricingService (Planned)

**Purpose**: Handle complex pricing logic, bulk price updates, price history.

**Potential Features**:
- Bulk price updates
- Price history tracking
- Dynamic pricing based on offers
- Price calculation with multiple discounts

### ProductSearchService (Planned)

**Purpose**: Advanced product search and filtering.

**Potential Features**:
- Full-text search
- Category-based filtering
- Price range filtering
- Stock availability filtering
- Supplier-based filtering

### ProductOfferService (Planned)

**Purpose**: Manage product-offer relationships and offer application.

**Note**: Offer application logic is currently handled by `CartOfferService` in the Cart domain. This service could be expanded for product-level offer management.

**Potential Features**:
- Apply offers to products
- Calculate offer-affected prices
- Validate offer eligibility
- Handle offer expiration
- Product-level offer preview (before adding to cart)

## Related Models

- `App\Models\Product` - Main product model
- `App\Models\Category` - Product categories (admin-managed)
- `App\Models\Manufacturer` - Product manufacturers (admin-managed)
- `App\Models\Company` - Supplier companies (with classification)
- `App\Models\OrderItem` - Order line items (with pricing_type)
- `App\Models\StockMovement` - Stock movement history
- `App\Models\Offer` - Product offers/promotions
- `App\Models\CartItem` - Cart items (with pricing_type)
- `App\Models\PriceList` - Custom per-buyer pricing entries

## Integration Points

### With Orders Domain

Products are used in orders:
- Order items reference products
- Product prices are used for order totals
- Product stock is deducted when orders are accepted

### With Inventory Domain

Products interact with inventory:
- Stock movements update product quantities
- Low stock detection triggers alerts
- Stock history is tracked per product

### With Offers Domain

Products participate in offers:
- Products can have multiple offers (many-to-many relationship via `offer_products` pivot table)
- Products include active offers in API responses (`offers` array)
- Offers affect product pricing in cart (automatically applied via `CartOfferService`)
- Offer eligibility is checked per product based on quantity requirements
- **Offer types** (offerType encodes discount type; no separate discountType): `buy_x_get_y`, `percentage_discount`, `fixed_discount`, `bundle_percentage`, `bundle_fixed`
- All offer types require `quantity_required` (minimum quantity to qualify)
- Product-specific overrides via pivot: `discount_override`, `quantity_required`, `quantity_free`
- **end_date** is optional—when omitted, offer has no time limit (active indefinitely after start_date)
- **is_active** controls whether offer is enabled

### With Cart Management

Products are added to carts:
- Cart items reference products
- Product prices (pharmacist_price) are used for cart totals
- Product availability is checked
- **Offers are automatically applied** when products are added to cart
- `CartOfferService` detects active offers and applies best offer (lowest total price)
- Cart items store `applied_offer_id` when offers are applied
- Offers are re-evaluated when cart quantities change or items are removed
- **Manual offer selection**: Users can view all available offers for a cart item and manually select a different offer (or remove manual selection to revert to automatic best offer)
- API endpoints: `GET /api/v1/cart/items/{id}/offers`, `POST /api/v1/cart/items/{id}/apply-offer`, `POST /api/v1/cart/items/{id}/remove-offer`

## Business Rules

1. **Product Creation**:
   - SKU must be unique
   - Supplier must exist
   - `pharmacist_price` is required
   - `category_id` must reference an existing category (if provided)
   - `manufacturer_id` must reference an existing manufacturer (if provided)
   - Categories and manufacturers are admin-managed (suppliers select from predefined lists)

2. **Pricing**:
   - Three tiers: wholesale (optional), pharmacist (required), customer (optional)
   - Wholesale price requires `wholesale_min_qty` — per-item quantity threshold
   - Product discount applies to both wholesale and pharmacist prices
   - Customer price is returned as-is (discount does not apply)
   - Custom price lists (per buyer) override tier-based pricing
   - API returns `pharmacistPriceAfterDiscount` when discount exists
   - API returns `wholesalePrice` and `wholesaleMinQty` for wholesale-capable suppliers

3. **Stock Management**:
   - Stock cannot go negative
   - Low stock warnings trigger at `min_stock_level`
   - Stock changes are tracked via movements

4. **Authorization**:
   - Suppliers can only manage their own products
   - Buyers can view all active products
   - Admins have full access

5. **Soft Deletes**:
   - Products use soft deletes
   - Deleted products are hidden from listings
   - Relationships are preserved

## Constants

- `App\Constants\DiscountType` - Discount type constants (`PERCENTAGE`, `FIXED`)
- `App\Constants\PricingType` - Pricing tier constants (`WHOLESALE`, `RETAIL`, `CUSTOM`)

## Observers

`StockMovementObserver` monitors product stock changes:
- Logs stock movements
- Triggers low stock warnings
- Maintains audit trail

## Testing Considerations

- Test product creation with various pricing scenarios
- Test price calculation methods
- Test discount application
- Test stock management
- Test category filtering (by category_id)
- Test manufacturer filtering (by manufacturer_id)
- Test category/manufacturer validation (must exist)
- Test search functionality
- Test authorization rules
- Test soft delete behavior

## Future Enhancements

- Product variants (sizes, colors, etc.)
- Product images and media
- Product reviews and ratings
- Product bundles and kits
- Product recommendations
- Product analytics and reporting
- Bulk import/export
- Product templates

