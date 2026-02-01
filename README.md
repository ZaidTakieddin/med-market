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

**Order Status Flow:**

-   `pending` → `accepted` | `cancelled`
-   `accepted` → `prepared` | `cancelled`
-   `prepared` → `shipped` | `cancelled`
-   `shipped` → `delivered` | `cancelled`

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
- `?is_active=true` - Filter by active status
- `?search=term` - Search in name, SKU, description, category name, or manufacturer name

**Note**: Products use `category_id` and `manufacturer_id` (foreign keys to admin-managed categories and manufacturers).

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

**Note**: Supplier companies include `categories` and `manufacturers` arrays (auto-synced from their products).

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
    "error": "Error message",
    "errors": {...}
}
```

**Paginated Response:**

```json
{
    "success": true,
    "data": [...],
    "pagination": {
        "current_page": 1,
        "per_page": 15,
        "total": 100,
        "last_page": 7,
        "from": 1,
        "to": 15
    }
}
```

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
```

The seeders automatically use the unpooled connection when configured, ensuring reliable transaction handling.

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

See [DEPLOYMENT.md](./DEPLOYMENT.md) for detailed deployment instructions.

## Development Guidelines

See `.cursor/rules/codebase_rules.mdc` for detailed development guidelines and architecture rules.

## Additional Documentation

-   [Deployment Guide](./DEPLOYMENT.md) - Production deployment instructions
-   [Progress Tracker](./PROGRESS.md) - Development progress and tasks
