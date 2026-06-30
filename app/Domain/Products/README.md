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

## Bulk Import (Excel/CSV)

Suppliers (and admins, per-company) can bulk create/update products by uploading an
`.xlsx`/`.csv` file. Processing is **asynchronous** and streamed in bounded-memory chunks,
so it scales to catalogs of any size.

### Endpoints

- `GET  /api/v1/products/import-sample` — download a ready-to-edit `.xlsx` sample with the
  exact headers and a few example rows.
- `POST /api/v1/products/import` — upload the file (`multipart/form-data`, field `file`;
  admins must also pass `supplier_company_id`). Returns `202` with the import record
  (`status=pending`).
- `GET  /api/v1/products/imports` — list your company's past imports (paginated).
- `GET  /api/v1/products/imports/{import}` — poll status, counts, and paginated per-row errors.

### Columns (heading row)

`name*`, `sku*`, `pharmacist_price*` (required); `description`, `category` (name),
`manufacturer` (name), `barcode`, `unit` (piece|box|bottle|pack|carton), `wholesale_price`,
`wholesale_min_qty`, `customer_price`, `discount`, `discount_type` (percentage|fixed),
`stock_quantity`, `min_stock_level`, `is_active`. The canonical list lives in
`ProductImportService::COLUMNS` and drives both the importer and the sample export.

### Behaviour & rules

- **Upsert by SKU**, scoped to the importing company. A SKU owned by **another** supplier
  is reported as a row error (never updated) — honouring company scoping. A SKU belonging to
  a soft-deleted product is also reported (restore it first).
- **Category/Manufacturer resolved by name** (case-insensitive). Unknown names error that
  row (they are admin-managed and never auto-created).
- **Stock**: `stock_quantity` sets the **initial** stock when a product is **created**
  (mirrors the single-create endpoint). On **update** stock is left untouched — stock
  changes go through the stock endpoints (rule #4: stock is never overwritten by import).
- **Update semantics are full-row**: on update, the sheet is authoritative — blank optional
  cells overwrite/clear the existing value (required fields can't be blank). Include every
  column you want to keep.
- Each row is validated; invalid rows are skipped and recorded with a clear message.
  Successful rows still import (partial success). Counts: `created_count`, `updated_count`,
  `failed_count`.

### Architecture

`ProductImportController@store` stores the upload and dispatches `ImportProductsJob`
(`imports` queue). That coordinator streams the file via `ProductRowsImport`
(maatwebsite/excel, `WithChunkReading`) — each chunk is a short queue job that delegates row
work to `ProductImportService`. The per-row `ProductObserver` taxonomy sync is **bypassed**
during the bulk write (`Product::withoutEvents`); a chained `FinalizeProductImportJob` runs
the sync **once** (via `SupplierTaxonomySyncService`) and sets the final status. See
[QUEUE_WORKERS.md](../../../docs/QUEUE_WORKERS.md).

## Product Images (gallery)

Products support a **gallery of images** (multiple per product, one primary, custom order).
Image **binaries are never stored in the database** — only `{disk, path, url}` are persisted
in the `product_images` table. The file is written to the configured media disk through
Laravel's `Storage` abstraction, so the backend is a single config switch.

### Storage backend (`config/media.php` → `MEDIA_DISK`)

- **`cloudinary`** (default, prod) — free tier with on-the-fly resizing, format
  optimization and CDN delivery. Set `CLOUDINARY_URL`. Requires
  `cloudinary-labs/cloudinary-laravel`.
- **`public`** (local/offline dev) — files in `storage/app/public` served via `APP_URL`
  (`php artisan storage:link`). No Cloudinary credentials needed.
- Any S3-compatible disk (e.g. Cloudflare R2) also works unchanged.

Because the binary lives on the disk and only the URL is recorded, image upload depends on
the storage service — not on the database being awake. The stored `url` is read directly
(no per-request Cloudinary API calls); `thumbnailUrl` injects a transformation on
Cloudinary and gracefully falls back to the full URL on other disks.

Verify the active backend is reachable and writable (uploads then deletes a 1×1 test
image) with: `php artisan media:check`.

### Endpoints

- `GET    /api/v1/products/{product}/images` — list (primary first).
- `POST   /api/v1/products/{product}/images` — upload (`multipart/form-data`, `images[]`;
  JPEG/PNG/WebP, ≤5 MB each, ≤8 per product). First image becomes primary.
- `DELETE /api/v1/products/{product}/images/{image}` — delete (promotes next if primary).
- `PATCH  /api/v1/products/{product}/images/reorder` — `ordered_ids` sets `sort_order`.
- `PATCH  /api/v1/products/{product}/images/{image}/primary` — set the cover image.

`ProductResource` returns an `images` array and `primaryImageUrl`.

### Architecture

`ProductImageController` (thin) delegates to `Products\ProductImageService`
(add/delete/reorder/set-primary; transactional DB writes; enforces exactly one primary and
the per-product cap), which uses the disk-agnostic `Media\ImageUploadService`
(+ `Media\StoredImage` DTO). Authorization reuses `ProductPolicy@update` (owning supplier
or admin); listing uses `view`. Company logos follow the same pattern via
`Companies\CompanyLogoService` (`POST`/`DELETE /companies/{company}/logo`, admin only).

## Related Models

- `App\Models\Product` - Main product model
- `App\Models\ProductImage` - Product gallery images (disk/path/url, is_primary, sort_order)
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
- Product reviews and ratings
- Product bundles and kits
- Product recommendations
- Product analytics and reporting
- Product templates

