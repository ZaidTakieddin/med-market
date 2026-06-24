# Med Market - B2B Medical Delivery System

A Laravel-based backend system for managing B2B medical product deliveries between suppliers and pharmacies.

## Tech Stack

-   **Framework**: Laravel 12
-   **Database**: PostgreSQL (Neon)
-   **Authentication**: Laravel Sanctum (Token-based)
-   **Cache**: File-based (Redis ready for production)
-   **PHP Version**: 8.4+

## Quick Start

### Local Development

1. **Install Dependencies**

    ```bash
    composer install
    ```

2. **Configure Environment**

    ```bash
    cp .env.example .env
    php artisan key:generate
    ```

    Update `.env` with your Neon database credentials.

3. **Run Migrations**

    ```bash
    php artisan migrate
    ```

4. **Seed Database (Optional)**

    ```bash
    php artisan db:seed
    ```

    See [Database Seeding](#database-seeding) section for configuration details.

5. **Start Development Server**
    ```bash
    php artisan serve
    ```

## Project Structure

The project follows a modular monolith architecture with domain-driven design principles:

```
app/
├── Domain/              # Domain-specific business logic
│   ├── Orders/         # Order management domain
│   ├── Inventory/      # Inventory management domain
│   ├── Companies/      # Company management domain
│   ├── Products/       # Product management domain
│   └── Deliveries/     # Delivery management domain
├── Services/           # Shared services
├── Jobs/               # Background jobs
├── Policies/           # Authorization policies
├── Observers/          # Model observers
└── Http/
    ├── Controllers/
    │   └── Api/        # API controllers (versioned)
    └── Requests/       # Form request validation
```

## API Documentation

### Swagger UI

-   **URL**: `/api/documentation`
-   **JSON**: `/docs/api-docs.json`
-   **Regenerate**: `php artisan l5-swagger:generate`

### Base Configuration

-   **Base URL**: `/api/v1`
-   **Authentication**: Bearer token (Sanctum)
-   **Response Format**: Standardized JSON with `success`, `data`, and `error` fields

**Pagination (all list endpoints):**
- Default: Paginated with `per_page=15` (max 100)
- To get all results without pagination: `?per_page=all` or `?paginate=false`

### Authentication Endpoints (`/api/v1/auth`)

| Method | Endpoint    | Description            | Auth Required |
| ------ | ----------- | ---------------------- | ------------- |
| POST   | `/register` | Register a new user    | No            |
| POST   | `/login`    | Login and get token    | No            |
| POST   | `/logout`   | Logout (revoke token)  | Yes           |
| GET    | `/me`       | Get authenticated user | Yes           |

**Rate Limiting**: Login endpoint limited to 5 requests per minute.

### Orders (`/api/v1/orders`)

| Method | Endpoint              | Description         | Auth Required | Role           |
| ------ | --------------------- | ------------------- | ------------- | -------------- |
| GET    | `/orders`             | List orders         | Yes           | All            |
| POST   | `/orders`             | Create order        | Yes           | Buyer          |
| GET    | `/orders/{id}`        | Get order details   | Yes           | All            |
| POST   | `/orders/{id}/status` | Update order status | Yes           | Supplier/Buyer |
| POST   | `/orders/{id}/cancel` | Cancel order        | Yes           | Supplier/Buyer |
| POST   | `/orders/{id}/assign-driver` | Assign driver to order | Yes | Supplier |
| GET    | `/orders/{id}/invoice` | Get invoice for order | Yes | All |

**Order Status Flow:**

-   `pending` → `accepted` | `cancelled`
-   `accepted` → `prepared` | `cancelled`
-   `prepared` → `shipped` | `cancelled`
-   `shipped` → `delivered` | `cancelled`

**Filtering Options:**
- `?status=pending` - Filter by status
- `?supplier_company_id=1` - Filter by supplier (for buyers)
- `?buyer_company_id=1` - Filter by buyer (for suppliers)
- `?from_date=2026-01-01` - Filter orders created from date (YYYY-MM-DD)
- `?to_date=2026-01-31` - Filter orders created up to date (YYYY-MM-DD)

### Products (`/api/v1/products`)

| Method | Endpoint         | Description         | Auth Required | Role     |
| ------ | ---------------- | ------------------- | ------------- | -------- |
| GET    | `/products`      | List products       | Yes           | All      |
| POST   | `/products`      | Create product      | Yes           | Supplier |
| GET    | `/products/{id}` | Get product details | Yes           | All      |
| PUT    | `/products/{id}` | Update product      | Yes           | Supplier |
| DELETE | `/products/{id}` | Delete product      | Yes           | Supplier |

**Filtering Options:**
- `?supplier_company_id=1` - Filter by supplier
- `?category_id=1` - Filter by category
- `?manufacturer_id=1` - Filter by manufacturer
- `?is_active=true` - Filter by active status
- `?search=term` - Search in name, SKU, description, category name, or manufacturer name

**Note**: Products use `category_id` and `manufacturer_id` (foreign keys to admin-managed categories and manufacturers). Products have two prices: `pharmacistPrice` (required) and `customerPrice` (optional). Product discount applies to pharmacist price only; `pharmacistPriceAfterDiscount` is returned when a discount exists.

### Offers (`/api/v1/offers`)

| Method | Endpoint         | Description         | Auth Required | Role     |
| ------ | ---------------- | ------------------- | ------------- | -------- |
| GET    | `/offers`        | List offers         | Yes           | All      |
| POST   | `/offers`        | Create offer        | Yes           | Supplier |
| GET    | `/offers/{id}`   | Get offer details   | Yes           | All      |
| PUT    | `/offers/{id}`   | Update offer        | Yes           | Supplier |
| DELETE | `/offers/{id}`   | Delete offer        | Yes           | Supplier |

**Offer types** (offerType encodes discount; no separate discountType): `buy_x_get_y`, `percentage_discount`, `fixed_discount`, `bundle_percentage`, `bundle_fixed`. **end_date** is optional (omit for unlimited offers). **is_active** controls whether the offer is enabled.

**Filtering Options:**
- `?supplier_company_id=1` - Filter by supplier
- `?offer_type=percentage_discount` - Filter by offer type
- `?is_active=true` - Filter by active status

### Stock Management (`/api/v1/products/{id}/stock`)

| Method | Endpoint     | Description                 | Auth Required | Role     |
| ------ | ------------ | --------------------------- | ------------- | -------- |
| GET    | `/movements` | View stock movement history | Yes           | All      |
| POST   | `/add`       | Add stock                   | Yes           | Supplier |
| POST   | `/adjust`    | Adjust stock                | Yes           | Supplier |

### Companies (`/api/v1/companies`)

| Method | Endpoint          | Description         | Auth Required | Role  |
| ------ | ----------------- | ------------------- | ------------- | ----- |
| GET    | `/companies`      | List companies      | Yes           | All   |
| POST   | `/companies`      | Create company      | Yes           | Admin |
| GET    | `/companies/{id}` | Get company details | Yes           | All   |
| PUT    | `/companies/{id}` | Update company      | Yes           | Admin |
| DELETE | `/companies/{id}` | Delete company      | Yes           | Admin |

**Filtering Options:**
- `?type=supplier` - Filter by company type
- `?category_id=1` - Filter suppliers by category (suppliers only)
- `?manufacturer_id=1` - Filter suppliers by manufacturer (suppliers only)
- `?search=term` - Search by company name, email, category names, or manufacturer names

**Note**: Supplier companies include `categories` and `manufacturers` arrays (auto-synced from their products). Companies include `description`, `address`, `latitude`, `longitude`, `region_id`, `address_line`, and `region` object (with city information).

### Cities (`/api/v1/cities`)

| Method | Endpoint        | Description         | Auth Required | Role  |
| ------ | --------------- | ------------------- | ------------- | ----- |
| GET    | `/cities`       | List cities         | Yes           | All   |
| GET    | `/cities/{id}`  | Get city details    | Yes           | All   |
| POST   | `/cities`       | Create city         | Yes           | Admin |
| PUT    | `/cities/{id}`  | Update city         | Yes           | Admin |
| DELETE | `/cities/{id}`  | Delete city         | Yes           | Admin |

**Filtering Options:**
- `?is_active=true` - Filter by active status
- `?search=term` - Search in name or description
- `?with_regions=true` - Include regions in response

**Note**: Cities are admin-managed. All authenticated users can list cities.

### Regions (`/api/v1/regions`)

| Method | Endpoint          | Description         | Auth Required | Role  |
| ------ | ----------------- | ------------------- | ------------- | ----- |
| GET    | `/regions`        | List regions        | Yes           | All   |
| GET    | `/regions/{id}`   | Get region details  | Yes           | All   |
| POST   | `/regions`        | Create region       | Yes           | Admin |
| PUT    | `/regions/{id}`   | Update region       | Yes           | Admin |
| DELETE | `/regions/{id}`   | Delete region       | Yes           | Admin |

**Filtering Options:**
- `?city_id=1` - Filter by city
- `?is_active=true` - Filter by active status
- `?search=term` - Search in name or description
- `?with_city=true` - Include city in response

**Note**: Regions are admin-managed and belong to cities. All authenticated users can list regions.

### Categories (`/api/v1/categories`)

| Method | Endpoint            | Description         | Auth Required | Role  |
| ------ | ------------------- | ------------------- | ------------- | ----- |
| GET    | `/categories`       | List categories     | Yes           | All   |
| GET    | `/categories/{id}`   | Get category details| Yes           | All   |
| POST   | `/categories`       | Create category     | Yes           | Admin |
| PUT    | `/categories/{id}`   | Update category     | Yes           | Admin |
| DELETE | `/categories/{id}`   | Delete category     | Yes           | Admin |

**Note**: Categories are admin-managed. All authenticated users can list categories for product creation.

### Manufacturers (`/api/v1/manufacturers`)

| Method | Endpoint              | Description         | Auth Required | Role  |
| ------ | --------------------- | ------------------- | ------------- | ----- |
| GET    | `/manufacturers`      | List manufacturers  | Yes           | All   |
| GET    | `/manufacturers/{id}` | Get manufacturer details| Yes        | All   |
| POST   | `/manufacturers`      | Create manufacturer  | Yes           | Admin |
| PUT    | `/manufacturers/{id}` | Update manufacturer  | Yes           | Admin |
| DELETE | `/manufacturers/{id}` | Delete manufacturer  | Yes           | Admin |

**Note**: Manufacturers are admin-managed. All authenticated users can list manufacturers for product creation.

### Drivers (`/api/v1/drivers`)

| Method | Endpoint                    | Description                    | Auth Required | Role     |
| ------ | --------------------------- | ------------------------------ | ------------- | -------- |
| GET    | `/drivers`                  | List drivers                   | Yes           | Supplier |
| POST   | `/drivers`                  | Create driver                  | Yes           | Supplier |
| GET    | `/drivers/{id}`             | Get driver details             | Yes           | Supplier |
| PUT    | `/drivers/{id}`             | Update driver                  | Yes           | Supplier |
| DELETE | `/drivers/{id}`             | Delete driver                  | Yes           | Supplier |
| GET    | `/drivers/{id}/orders`      | Get driver's orders            | Yes           | Supplier |
| GET    | `/drivers/{id}/regions`     | Get driver's assigned regions  | Yes           | Supplier |
| POST   | `/drivers/{id}/regions`     | Assign regions to driver       | Yes           | Supplier |
| DELETE | `/drivers/{id}/regions/{region_id}` | Remove region from driver | Yes | Supplier |

**Note**: Suppliers can manage their own drivers and assign regions to them. Drivers can serve multiple regions.

### Cart (`/api/v1/cart`)

| Method | Endpoint        | Description                    | Auth Required | Role |
| ------ | --------------- | ------------------------------ | ------------- | ---- |
| GET    | `/cart`         | Get cart (grouped by supplier)  | Yes           | Buyer |
| POST   | `/cart/items`   | Add item to cart               | Yes           | Buyer |
| PUT    | `/cart/items/{id}` | Update cart item            | Yes           | Buyer |
| DELETE | `/cart/items/{id}` | Remove cart item            | Yes           | Buyer |
| DELETE | `/cart`         | Clear cart                     | Yes           | Buyer |
| POST   | `/cart/checkout` | Checkout (creates orders)    | Yes           | Buyer |
| POST   | `/cart/checkout-supplier` | Checkout items for specific supplier | Yes | Buyer |
| GET    | `/cart/items/{id}/offers` | Get available offers for cart item | Yes | Buyer |
| POST   | `/cart/items/{id}/apply-offer` | Apply specific offer to cart item | Yes | Buyer |
| POST   | `/cart/items/{id}/remove-offer` | Remove manual offer selection | Yes | Buyer |

**Note**: 
- Cart response includes `itemsBySupplier` array (items grouped by supplier) matching checkout behavior. If cart contains items from multiple suppliers, checkout creates multiple orders (one per supplier).
- **Supplier-Specific Checkout**: Use `/cart/checkout-supplier` with `supplier_id` in request body to checkout only items from a specific supplier. Other items remain in the cart and totals are recalculated.
- **Offers are automatically applied** when adding/updating cart items. The system detects active offers, checks if quantity requirements are met, and applies the best offer (lowest total price). Cart items include `appliedOfferId` when an offer is applied.
- **Manual Offer Selection**: Users can view all available offers for a cart item and manually select a different offer (or remove the manual selection to revert to automatic best offer).

### Invoices (`/api/v1/invoices`)

| Method | Endpoint        | Description                    | Auth Required | Role           |
| ------ | --------------- | ------------------------------ | ------------- | -------------- |
| GET    | `/invoices`     | List invoices                 | Yes           | All            |
| GET    | `/invoices/{id}` | Get invoice details          | Yes           | All            |
| GET    | `/invoices/{id}/download` | Download invoice file | Yes | All |
| POST   | `/invoices/{id}/send` | Mark invoice as sent | Yes | Supplier |
| POST   | `/invoices/{id}/pay` | Mark invoice as paid | Yes | Buyer/Admin |

**Filtering Options:**
- `?order_id=1` - Filter by order ID
- `?status=generated` - Filter by status (generated, sent, paid, overdue, cancelled)
- `?payment_status=pending` - Filter by payment status (pending, paid, partial, overdue, refunded)
- `?from_date=2026-01-01` - Filter invoices created from date (YYYY-MM-DD)
- `?to_date=2026-01-31` - Filter invoices created up to date (YYYY-MM-DD)

**Note**: 
- Invoices are automatically generated when orders are accepted.
- Buyers see invoices for their orders; suppliers see invoices for orders they fulfill.
- Invoice statuses: `generated`, `sent`, `paid`, `overdue`, `cancelled`.
- Payment statuses: `pending`, `paid`, `partial`, `overdue`, `refunded`.

### Authentication

All protected endpoints require a Bearer token in the Authorization header:

```
Authorization: Bearer {your-token}
```

Tokens are obtained via `/api/v1/auth/login` or `/api/v1/auth/register`.

### Response Format

**Success Response:**

```json
{
    "success": true,
    "data": {...},
    "message": "Optional message"
}
```

**Error Response:**

```json
{
    "success": false,
    "error": {
        "title": "Bad request",
        "message": "Human-readable error message"
    },
    "errors": {...}
}
```

The `error.title` is a short status label derived from the HTTP status code;
`error.message` is the human-readable detail. `errors` is present only for
validation failures (422), keyed by field name.

**Paginated Response:**

```json
{
    "success": true,
    "data": [...],
    "pagination": {
        "currentPage": 1,
        "lastPage": 7,
        "perPage": 15,
        "total": 100,
        "from": 1,
        "to": 15
    }
}
```

## Location Management

The system includes a hierarchical location management system:

-   **Cities**: Admin-managed cities (e.g., Damascus, Aleppo, Homs)
-   **Regions**: Admin-managed regions belonging to cities (e.g., Mazzeh, Malki, Rawda in Damascus)
-   **Companies**: Use `region_id` to specify their location, include coordinates (latitude, longitude)
-   **Drivers**: Can be assigned to multiple regions by suppliers
-   **Orders**: Include pharmacy region information for better driver assignment

### Endpoints

-   `GET /api/v1/cities` - List cities (all users)
-   `GET /api/v1/regions` - List regions (all users, filter by city_id)
-   `GET /api/v1/drivers/{id}/regions` - Get driver's assigned regions
-   `POST /api/v1/drivers/{id}/regions` - Assign regions to driver
-   `DELETE /api/v1/drivers/{id}/regions/{region_id}` - Remove region from driver

## Database Seeding

### Connection Strategy

The application uses different database connections for different operations:

-   **Pooled Connection** (`-pooler` suffix): Used for API requests (high concurrency)
-   **Unpooled Connection** (direct): Used for seeders and migrations (reliable transactions)

### Configuration

Add these to your `.env` file:

```env
# Default connection (pooled - for API)
DB_CONNECTION=pgsql
DB_HOST=ep-xxx-pooler.c-2.eu-central-1.aws.neon.tech
DB_PORT=5432
DB_DATABASE=neondb
DB_USERNAME=neondb_owner
DB_PASSWORD=your_password
DB_SSLMODE=require

# Seeder connection (unpooled - for seeding)
DB_SEEDER_HOST=ep-xxx.c-2.eu-central-1.aws.neon.tech
DB_SEEDER_PORT=5432
DB_SEEDER_DATABASE=neondb
DB_SEEDER_USERNAME=neondb_owner
DB_SEEDER_PASSWORD=your_password
```

**Note**: To get the unpooled connection from Neon, use the "Direct connection" (without `-pooler` suffix).

### Usage

```bash
# Seed all data
php artisan db:seed

# Seed individual seeder
php artisan db:seed --class=OrderSeeder

# Sync supplier categories and manufacturers (if needed)
# Usually not needed as ProductObserver handles this automatically
php artisan suppliers:sync-categories-manufacturers

# Sync specific supplier
php artisan suppliers:sync-categories-manufacturers --supplier-id=1
```

The seeders automatically use the unpooled connection when configured, ensuring reliable transaction handling.

**Note**: The `suppliers:sync-categories-manufacturers` command is usually not needed as the ProductObserver automatically syncs supplier categories and manufacturers when products are created, updated, or deleted. However, it's useful for initial data migration or fixing inconsistencies.

## Architecture Principles

-   **API-First**: All endpoints designed for API consumption
-   **Domain-Driven**: Business logic lives in Domain classes, not controllers
-   **Thin Controllers**: Controllers only validate input and call domain/services
-   **Soft Deletes**: Critical data (orders, invoices, stock movements) use soft deletes
-   **Transactional**: All stock and order changes are transactional
-   **Audit Trail**: All state changes are logged

## Authentication & Authorization

-   **Token-Based**: Using Laravel Sanctum
-   **Role-Based**: Admin, Supplier, Buyer (Pharmacist), Driver
-   **Company-Based**: Authorization considers both role and company membership

## Troubleshooting

### Transaction Errors

If you encounter transaction errors (`SQLSTATE[25P02]`), the application includes a transaction cleanup middleware that automatically handles failed transactions. This is especially important with Neon's connection pooling.

**Current Setup**: File-based cache eliminates transaction conflicts.

**For Production**: Consider switching to Redis cache:

```env
CACHE_STORE=redis
REDIS_HOST=your-redis-host
REDIS_PASSWORD=your-redis-password
REDIS_PORT=6379
```

### Common Issues

**500 Error**: Check environment variables, database connection, and set `APP_DEBUG=true` temporarily.

**Database Connection Failed**: Verify Neon connection string, ensure `DB_SSLMODE=require`, and check credentials.

**Swagger Mixed Content**: Ensure `APP_URL` is set to HTTPS URL in production.

## Production Deployment

See [docs/DEPLOYMENT.md](./docs/DEPLOYMENT.md) for detailed deployment instructions.

## Development Guidelines

See `.cursor/rules/codebase_rules.mdc` for detailed development guidelines and architecture rules.

## Additional Documentation

-   [Deployment Guide](./docs/DEPLOYMENT.md) - Production deployment instructions
-   [Queue Workers](./docs/QUEUE_WORKERS.md) - Queue setup and worker configuration
-   [Progress Tracker](./docs/PROGRESS.md) - Development progress and tasks
