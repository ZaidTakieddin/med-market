# Med Market - B2B Medical Delivery System

A Laravel-based backend system for managing B2B medical product deliveries between suppliers and pharmacies.

## Tech Stack

-   **Framework**: Laravel 12
-   **Database**: PostgreSQL (Neon)
-   **Authentication**: Laravel Sanctum (Token-based)
-   **PHP Version**: 8.4+

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

## Database Configuration

The project is configured to use Neon PostgreSQL. Connection details are stored in `.env`:

-   **Host**: Neon managed PostgreSQL instance
-   **Database**: neondb
-   **SSL Mode**: Required

## API Structure

-   **Base URL**: `/api/v1`
-   **Authentication**: Bearer token (Sanctum)
-   **Response Format**: Standardized JSON responses with `success`, `data`, and `error` fields

### Response Examples

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

## Setup Instructions

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

5. **Start Development Server**
    ```bash
    php artisan serve
    ```

### Production Deployment

See [DEPLOYMENT.md](./DEPLOYMENT.md) for detailed deployment instructions to free hosting platforms like Render, Railway, or Fly.io.

## Architecture Principles

-   **API-First**: All endpoints are designed for API consumption
-   **Domain-Driven**: Business logic lives in Domain classes, not controllers
-   **Thin Controllers**: Controllers only validate input and call domain/services
-   **Soft Deletes**: Critical data (orders, invoices, stock movements) use soft deletes
-   **Transactional**: All stock and order changes are transactional
-   **Audit Trail**: All state changes are logged

## Authentication & Authorization

-   **Token-Based**: Using Laravel Sanctum
-   **Role-Based**: Admin, Supplier, Buyer (Pharmacist), Driver
-   **Company-Based**: Authorization considers both role and company membership

## Order States

Orders follow explicit state transitions:

-   `pending` → `accepted` → `prepared` → `shipped` → `delivered`
-   `cancelled` (can occur from any state with validation)

## Next Steps

1. Set up domain models and migrations
2. Implement authentication endpoints
3. Create company and user management
4. Build order management system
5. Implement inventory tracking
6. Set up delivery management

## Development Guidelines

See `.cursor/rules/codebase_rules.mdc` for detailed development guidelines and architecture rules.
