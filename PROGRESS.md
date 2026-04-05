# Med Market - Development Progress Tracker

> Last Updated: 2026-04-03 (Phase 1/2 comprehensive audit: 11 issues fixed across controllers, seeders, form requests, resources)

### Related Documentation

- [README.md](./README.md) - Project overview, setup, API docs
- [DEPLOYMENT.md](./DEPLOYMENT.md) - Production deployment guide
- [QUEUE_WORKERS.md](./QUEUE_WORKERS.md) - Queue workers setup and configuration
- [app/Domain/Orders/README.md](./app/Domain/Orders/README.md) - Order domain
- [app/Domain/Inventory/README.md](./app/Domain/Inventory/README.md) - Inventory domain
- [app/Domain/Products/README.md](./app/Domain/Products/README.md) - Product domain
- [app/Domain/Companies/README.md](./app/Domain/Companies/README.md) - Company domain
- [app/Domain/Deliveries/README.md](./app/Domain/Deliveries/README.md) - Deliveries domain
- [app/Domain/Pricing/README.md](./app/Domain/Pricing/README.md) - Pricing domain (NEW)

## 📋 Table of Contents

-   [Completed Features](#completed-features)
-   [In Progress](#in-progress)
-   [Pending Tasks](#pending-tasks)
-   [Future Requests](#future-requests)
-   [Notes & Decisions](#notes--decisions)

---

## ✅ Completed Features

### Core Infrastructure

-   [x] Laravel 12 project initialized
-   [x] Neon Postgres database configured and connected
-   [x] All database migrations completed (16 tables)
-   [x] Laravel Sanctum installed for token-based authentication
-   [x] API versioning structure (`/api/v1`)
-   [x] Base API controller with standardized responses
-   [x] Directory structure following modular monolith architecture
-   [x] Transaction cleanup middleware for PostgreSQL connection pooling
-   [x] Exception handling for API routes (JSON responses)
-   [x] File-based cache configuration (Redis ready)

### Database Schema

-   [x] Users table with company and role support
-   [x] Companies table (suppliers & buyers) with address, coordinates, region_id
-   [x] Products table with stock tracking, category_id, manufacturer_id
-   [x] Categories table (admin-managed)
-   [x] Manufacturers table (admin-managed)
-   [x] Cities table (admin-managed)
-   [x] Regions table (admin-managed, belongs to city)
-   [x] Driver regions pivot table (driver-region assignments)
-   [x] Supplier categories pivot table (auto-synced)
-   [x] Supplier manufacturers pivot table (auto-synced)
-   [x] Orders table with status management
-   [x] Order items table
-   [x] Stock movements table (audit trail)
-   [x] Order status logs table (audit trail)
-   [x] Personal access tokens table (Sanctum)
-   [x] Cache, jobs, sessions tables
-   [x] Company classifications (supplier_classification, buyer_classification, min_order_amount)
-   [x] Product wholesale pricing (wholesale_price, wholesale_min_qty)
-   [x] Pricing type tracking on cart_items and order_items
-   [x] Price lists table (custom per-buyer pricing)
-   [x] Buyer-supplier relationships table (payment terms, credit limits)
-   [x] Salesman regions pivot table
-   [x] Salesman clients table (salesman-pharmacy assignments)
-   [x] Order payment fields (payment_method, payment_due_date, placed_by_salesman_id)

### Models & Relationships

-   [x] User model with Sanctum integration
-   [x] Company model with relationships
-   [x] Product model with stock tracking, category, manufacturer relationships
-   [x] Category model with products relationship
-   [x] Manufacturer model with products relationship
-   [x] City model with regions relationship
-   [x] Region model with city, companies, and drivers relationships
-   [x] Company model with categories, manufacturers, and region relationships (auto-synced)
-   [x] User model with driverRegions relationship (for drivers)
-   [x] Order model with state management
-   [x] OrderItem model
-   [x] StockMovement model
-   [x] OrderStatusLog model
-   [x] All model relationships defined

### Constants & Enums

-   [x] OrderStatus constants with transition validation
-   [x] UserRole constants (admin, supplier, buyer, driver, salesman)
-   [x] CompanyType constants (supplier, buyer)
-   [x] SupplierClassification constants (wholesale, retail, hybrid)
-   [x] BuyerClassification constants (pharmacy, salesman)
-   [x] PricingType constants (wholesale, retail, custom)
-   [x] PaymentTerms constants (cash, net_15, net_30, net_60)
-   [x] StockMovementType constants

### Domain Services

-   [x] OrderCreationService - Creates orders with items and calculates totals
-   [x] OrderStateTransitionService - Handles order state transitions with validation and logging
-   [x] StockMovementService - Records stock movements and updates inventory
-   [x] PricingService - Central price resolution engine (custom price list → wholesale → retail → discount)
-   [x] PriceResult DTO - Immutable result object with unitPrice, originalPrice, pricingType, discountAmount, wholesaleEligible, minQtyMet

### Authentication System

-   [x] Register endpoint (`POST /api/v1/auth/register`)
-   [x] Login endpoint (`POST /api/v1/auth/login`) with rate limiting
-   [x] Logout endpoint (`POST /api/v1/auth/logout`)
-   [x] Get authenticated user (`GET /api/v1/auth/me`)
-   [x] Form request validation (RegisterRequest, LoginRequest)

### Enums API

-   [x] Get all enums (`GET /api/v1/enums`) - Returns offerType, orderStatus, userRole, companyType, stockMovementType, discountType
-   [x] Optional filter (`?type=offerType`) - Return single enum type

### Order Management API

-   [x] List orders (`GET /api/v1/orders`) - Filtered by company, status, date range
-   [x] Create order (`POST /api/v1/orders`) - Buyers only
-   [x] Get order details (`GET /api/v1/orders/{id}`)
-   [x] Update order status (`POST /api/v1/orders/{id}/status`)
-   [x] Cancel order (`POST /api/v1/orders/{id}/cancel`)
-   [x] Assign driver (`POST /api/v1/orders/{id}/assign-driver`) - With region-based suggestions
-   [x] Form request validation (CreateOrderRequest, UpdateOrderStatusRequest)
-   [x] Date range filtering (`?from_date=YYYY-MM-DD&to_date=YYYY-MM-DD`)

### Product Management API

-   [x] List products (`GET /api/v1/products`) - With search and filtering
-   [x] Filter products by category_id (`?category_id=1`)
-   [x] Filter products by manufacturer_id (`?manufacturer_id=1`)
-   [x] Create product (`POST /api/v1/products`) - Suppliers only
-   [x] Get product details (`GET /api/v1/products/{id}`)
-   [x] Update product (`PUT /api/v1/products/{id}`)
-   [x] Delete product (`DELETE /api/v1/products/{id}`)
-   [x] Form request validation (CreateProductRequest, UpdateProductRequest)
-   [x] Products use category_id and manufacturer_id (foreign keys, admin-managed)
-   [x] Product search includes category and manufacturer names

### Company Management API

-   [x] List companies (`GET /api/v1/companies`) - With filtering
-   [x] Create company (`POST /api/v1/companies`) - Admin only
-   [x] Get company details (`GET /api/v1/companies/{id}`)
-   [x] Update company (`PUT /api/v1/companies/{id}`)
-   [x] Delete company (`DELETE /api/v1/companies/{id}`)
-   [x] Form request validation (CreateCompanyRequest, UpdateCompanyRequest)
-   [x] Filter suppliers by category_id (`?category_id=1`)
-   [x] Filter suppliers by manufacturer_id (`?manufacturer_id=1`)
-   [x] Search suppliers by category/manufacturer names
-   [x] Companies include categories and manufacturers arrays (auto-synced from products)
-   [x] Companies include address, coordinates (latitude, longitude), region_id, address_line
-   [x] Companies include region object with city information

### Category Management API (Admin Only)

-   [x] List categories (`GET /api/v1/categories`) - Available to all authenticated users
-   [x] Get category details (`GET /api/v1/categories/{id}`)
-   [x] Create category (`POST /api/v1/categories`) - Admin only
-   [x] Update category (`PUT /api/v1/categories/{id}`) - Admin only
-   [x] Delete category (`DELETE /api/v1/categories/{id}`) - Admin only
-   [x] Form request validation (CreateCategoryRequest, UpdateCategoryRequest)
-   [x] Categories include slug, description, sort_order, is_active

### Manufacturer Management API (Admin Only)

-   [x] List manufacturers (`GET /api/v1/manufacturers`) - Available to all authenticated users
-   [x] Get manufacturer details (`GET /api/v1/manufacturers/{id}`)
-   [x] Create manufacturer (`POST /api/v1/manufacturers`) - Admin only
-   [x] Update manufacturer (`PUT /api/v1/manufacturers/{id}`) - Admin only
-   [x] Delete manufacturer (`DELETE /api/v1/manufacturers/{id}`) - Admin only
-   [x] Form request validation (CreateManufacturerRequest, UpdateManufacturerRequest)
-   [x] Manufacturers include slug, description, country, website, email, phone, sort_order, is_active

### Authorization & Security

-   [x] UserPolicy - User management permissions
-   [x] CompanyPolicy - Company management permissions
-   [x] ProductPolicy - Product CRUD permissions
-   [x] OrderPolicy - Order management with state-specific permissions
-   [x] Policies registered in AppServiceProvider
-   [x] Role-based and company-based authorization

### API Documentation

-   [x] L5-Swagger installed and configured
-   [x] OpenAPI 3.0 annotations on all endpoints
-   [x] Swagger UI available at `/api/documentation`
-   [x] All endpoints documented with request/response examples
-   [x] Authentication scheme documented
-   [x] Stock management endpoints documented
-   [x] Product endpoints updated with new fields (category_id, manufacturer_id, pharmacist_price, customer_price, discount, discount_type)
    -   [x] Create product endpoint includes all new fields with descriptions
    -   [x] Update product endpoint includes all new fields
    -   [x] List products endpoint documents category_id, manufacturer_id filtering
    -   [x] Get product endpoint shows all new fields in response schema (includes category and manufacturer objects)
-   [x] Category endpoints documented (admin CRUD, public list/show)
-   [x] Manufacturer endpoints documented (admin CRUD, public list/show)
-   [x] City endpoints documented (admin CRUD, public list/show)
-   [x] Region endpoints documented (admin CRUD, public list/show)
-   [x] Company endpoints updated with category_id, manufacturer_id, address, coordinates, region_id
-   [x] Order endpoints updated with multi-supplier order handling and date range filtering
-   [x] Driver endpoints updated with region management (assign regions, get regions, remove region)
    -   [x] List orders endpoint documents multi-supplier context and filtering
    -   [x] Create order endpoint clarifies single-supplier vs cart checkout
    -   [x] Get order endpoint explains single order per supplier
    -   [x] Status update and cancel endpoints document independent order management
-   [x] Cart endpoints documented with multi-supplier checkout explanation
    -   [x] Add item endpoint explains automatic price calculation using pharmacist_price
    -   [x] Checkout endpoint documents automatic order splitting by supplier
-   [x] Driver assignment endpoint documented with authorization details
-   [x] All Swagger docs reflect latest product enhancements and multi-supplier order system

### Observers & Automatic Logging

-   [x] OrderObserver - Automatic order status change logging
-   [x] StockMovementObserver - Automatic inventory change logging
-   [x] ProductObserver - Automatic supplier categories/manufacturers sync
    -   [x] Syncs when products are created, updated, deleted, or restored
    -   [x] Only syncs from active products with category_id/manufacturer_id
    -   [x] Handles supplier changes (syncs both old and new suppliers)
-   [x] Observers registered in AppServiceProvider
-   [x] All state changes logged to OrderStatusLog table
-   [x] All stock movements logged with context
-   [x] Supplier categories/manufacturers auto-synced from products (suppliers cannot manually control)

### Database Seeders

-   [x] CompanySeeder - Seeds suppliers and buyers with Arabic names and addresses
-   [x] UserSeeder - Seeds users with different roles (admin, supplier, buyer, driver) with Arabic names
    -   [x] Idempotent seeding (updateOrCreate based on email)
    -   [x] Multiple drivers per supplier (if multiple suppliers exist)
-   [x] CategorySeeder - Seeds product categories (admin-managed)
    -   [x] Seeds 9 categories (مسكنات, مضادات حيوية, etc.)
    -   [x] Idempotent seeding (updateOrCreate based on name)
-   [x] ManufacturerSeeder - Seeds manufacturers (admin-managed)
    -   [x] Seeds 5 manufacturers with Arabic names
    -   [x] Idempotent seeding (updateOrCreate based on name)
-   [x] ProductSeeder - Seeds products with stock, Arabic names and descriptions
    -   [x] Updated to use category_id (foreign key) instead of string category
    -   [x] Updated to use manufacturer_id (foreign key)
    -   [x] Updated with pharmacist_price and customer_price (dual pricing)
    -   [x] Updated with discount and discount_type fields
    -   [x] Idempotent seeding (updateOrCreate based on SKU)
-   [x] OfferSeeder - Seeds offers/promotions with Arabic names and descriptions
    -   [x] Buy X Get Y offers
    -   [x] Percentage discount offers
    -   [x] Fixed discount offers
    -   [x] Bundle offers (bundle_percentage, bundle_fixed)
    -   [x] Associates offers with products
-   [x] OrderSeeder - Seeds sample orders with Arabic notes
-   [x] CitySeeder - Seeds cities (Damascus, Aleppo, Homs, etc.) with Arabic names
-   [x] RegionSeeder - Seeds regions for each city (Mazzeh, Malki, Rawda for Damascus, etc.)
-   [x] BuyerSupplierRelationshipSeeder - Seeds buyer-supplier relationships, price lists, salesman-pharmacy clients
    -   [x] Credit terms (cash, net_15, net_30, net_60) with credit limits
    -   [x] Custom price list entries for special customers
    -   [x] Salesman-pharmacy client assignments (supplier-associated and independent)
-   [x] DatabaseSeeder - Orchestrates all seeders in correct order (cities → regions → companies → ... → relationships → orders)
-   [x] Separate database configuration for seeders (unpooled connection)
-   [x] All seeders use Arabic data for names, addresses, and descriptions
-   [x] All seeders are idempotent (can be run multiple times safely)
-   [x] CompanySeeder updated with supplier_classification and buyer_classification
    -   [x] Supplier 1: retail, Supplier 2: hybrid, Supplier 3: wholesale, Supplier 4: retail
    -   [x] New salesman buyer company (مؤسسة الأمل للتوزيع الطبي)
-   [x] UserSeeder updated with salesman users
    -   [x] Supplier-associated salesmen (belong to supplier companies, like drivers)
    -   [x] Independent salesman (belongs to salesman buyer company)
-   [x] ProductSeeder updated with wholesale pricing
    -   [x] Supplier 2 (hybrid) products have wholesale_price and wholesale_min_qty
    -   [x] Supplier 3 (wholesale) products have wholesale_price and wholesale_min_qty

### Infrastructure & Reliability

-   [x] Transaction cleanup middleware (CleanupTransactions) - Handles failed transactions
-   [x] API exception handling - Proper JSON error responses for unauthenticated requests
-   [x] File-based cache configuration - Eliminates transaction conflicts
-   [x] Code cleanup - Removed commented code, fixed imports, standardized constants

### Documentation

-   [x] README.md - Consolidated project documentation (includes API docs, seeding, troubleshooting)
-   [x] DEPLOYMENT.md - Production deployment guide
-   [x] PROGRESS.md - Development progress tracker
-   [x] QUEUE_WORKERS.md - Queue workers setup and configuration guide
-   [x] Domain READMEs - Complete documentation for all domains
    -   [x] `app/Domain/Orders/README.md` - Order domain documentation
    -   [x] `app/Domain/Inventory/README.md` - Inventory domain documentation
    -   [x] `app/Domain/Products/README.md` - Product domain documentation
    -   [x] `app/Domain/Companies/README.md` - Company domain documentation
    -   [x] `app/Domain/Deliveries/README.md` - Deliveries domain documentation
    -   [x] `app/Domain/Pricing/README.md` - Pricing domain documentation (NEW)

---

## 🔄 In Progress

### Supplier Classifications & Multi-Tier Pricing

Phases 1–5 complete. Remaining phase:

- [x] **Phase 1**: Data model — migrations, constants, models, seeders ✅
- [x] **Phase 2**: PricingService — central price resolution engine ✅
- [x] **Phase 3**: Cart & order integration with PricingService + catalog visibility ✅
- [x] **Phase 4**: Buyer-supplier relationship & credit management + price list endpoints ✅
- [x] **Phase 5**: Salesman features (management, region assignment, client management, on-behalf ordering) ✅
- [ ] **Phase 6**: Final polish — Swagger generation, seeders validation, documentation review

---

## 📝 Pending Tasks

### High Priority

#### 1. Model Observers ⚠️ Required by Rules ✅ COMPLETED

-   [x] OrderObserver
    -   [x] Auto-log order status changes
    -   [x] Log order creation, updates, deletions
    -   [x] Register in AppServiceProvider
-   [x] StockMovementObserver
    -   [x] Log inventory changes
    -   [x] Log low stock warnings
    -   [x] Register in AppServiceProvider
-   [x] ProductObserver
    -   [x] Auto-sync supplier categories/manufacturers when products change
    -   [x] Handles product creation, updates, deletions, restorations
    -   [x] Register in AppServiceProvider

#### 2. Stock Management Endpoints ✅ COMPLETED

-   [x] `GET /api/v1/products/{id}/stock/movements` - View stock movement history
-   [x] `POST /api/v1/products/{id}/stock/add` - Add stock
-   [x] `POST /api/v1/products/{id}/stock/adjust` - Adjust stock (suppliers)
-   [x] Form request validation for stock operations (AddStockRequest, AdjustStockRequest)
-   [x] Swagger documentation
-   [x] Authorization checks (suppliers only, own products)

#### 3. Automatic Stock Deduction ✅ COMPLETED

-   [x] Deduct stock when order status changes to `accepted` or `prepared`
-   [x] Revert stock if order is cancelled
-   [x] Implemented in OrderStateTransitionService
-   [x] Handle edge cases (insufficient stock throws exception, transaction rollback)
-   [x] Stock deduction happens in same transaction as status change

#### 4. Database Seeders ✅ COMPLETED

-   [x] CompanySeeder - Seed suppliers and buyers with Arabic data
-   [x] ProductSeeder - Seed products with stock and Arabic names
    -   [x] Updated with category field
    -   [x] Updated with pharmacist_price and customer_price (dual pricing)
    -   [x] Updated with discount and discount_type fields
    -   [x] Idempotent seeding (updateOrCreate based on SKU)
-   [x] OfferSeeder - Seed offers/promotions with Arabic names
    -   [x] Creates various offer types (buy_x_get_y, percentage_discount, fixed_discount, bundle_percentage, bundle_fixed)
    -   [x] Associates offers with products
    -   [x] Sets active date ranges
-   [x] OrderSeeder - Seed sample orders with Arabic notes
-   [x] UserSeeder - Seed users with different roles and Arabic names
    -   [x] Idempotent seeding (updateOrCreate based on email)
    -   [x] Multiple drivers per supplier
-   [x] DatabaseSeeder - Orchestrates all seeders in correct order
-   [x] Separate database configuration for seeders (unpooled connection)
-   [x] All data in Arabic (names, addresses, descriptions, notes)
-   [x] All seeders are idempotent (can be run multiple times safely)

### Medium Priority

#### 5. Background Jobs ✅ COMPLETED

-   [x] SendOrderNotificationJob - Email notifications on status changes
    -   [x] Dispatched automatically from OrderStateTransitionService
    -   [x] Sends notifications to buyer and supplier company users
    -   [x] Retry logic: 3 tries, 60s backoff
-   [x] GenerateInvoiceJob - Invoice generation
    -   [x] Dispatched automatically when order status changes to accepted
    -   [x] Generates invoice files (text format, ready for PDF conversion)
    -   [x] Saves invoices to storage
    -   [x] Retry logic: 3 tries, 120s backoff
-   [x] LowStockAlertJob - Alert when stock is low
    -   [x] Dispatched automatically from StockMovementObserver
    -   [x] Sends alerts to supplier company users
    -   [x] Retry logic: 2 tries, 300s backoff
-   [x] ExportOrdersJob - Heavy exports
    -   [x] Supports CSV and JSON formats
    -   [x] Filterable by company, status, date range
    -   [x] Saves exports to storage
    -   [x] Retry logic: 2 tries, 300s backoff
-   [x] Configure queue connection
    -   [x] Database queue configured (default)
    -   [x] Redis queue ready for production
    -   [x] Queue configuration documented
-   [x] Set up queue workers
    -   [x] Queue workers documentation created (QUEUE_WORKERS.md)
    -   [x] Supervisor/systemd configuration examples
    -   [x] Production deployment instructions

#### 6. Domain READMEs ⚠️ Required by Rules ✅ COMPLETED

-   [x] `app/Domain/Orders/README.md` - Order domain documentation
    -   [x] Documents OrderCreationService, OrderStateTransitionService, OrderState
    -   [x] Explains order lifecycle, state transitions, multi-supplier handling
    -   [x] Includes usage examples and business rules
-   [x] `app/Domain/Inventory/README.md` - Inventory domain documentation
    -   [x] Documents StockMovementService
    -   [x] Explains stock movements, audit trail, stock safety
    -   [x] Includes usage examples and integration points
-   [x] `app/Domain/Products/README.md` - Product domain documentation
    -   [x] Documents product structure, pricing, discounts, categories
    -   [x] Explains current implementation and future services
    -   [x] Includes integration points and business rules
-   [x] `app/Domain/Companies/README.md` - Company domain documentation
    -   [x] Documents company types, multi-tenancy, relationships
    -   [x] Explains current implementation and future services
    -   [x] Includes authorization rules and integration points
-   [x] `app/Domain/Deliveries/README.md` - Deliveries domain documentation
    -   [x] Documents driver management and delivery concepts
    -   [x] Explains current implementation and planned services
    -   [x] Includes future enhancements and integration points

#### 7. Error Handling Improvements ✅ COMPLETED

-   [x] API exception handling - JSON responses for unauthenticated requests
-   [x] Route not found handling - Proper 401/404 JSON responses
-   [x] Custom exception handler (enhanced)
    -   [x] Created `app/Exceptions/Handler.php` with comprehensive exception handling
    -   [x] Handles ValidationException, AuthenticationException, AuthorizationException
    -   [x] Handles ModelNotFoundException, NotFoundHttpException, RouteNotFoundException
    -   [x] Handles InvalidArgumentException (from domain services)
    -   [x] Handles QueryException with specific error types (unique constraints, foreign keys, transactions)
    -   [x] Generic exception handling with proper logging
-   [x] Better validation error messages
    -   [x] Created `app/Http/Requests/BaseFormRequest.php` for consistent validation error formatting
    -   [x] All FormRequest classes now extend BaseFormRequest
    -   [x] Standardized validation error response format
    -   [x] Improved error messages with field-level details
-   [x] API error response standardization (enhanced)
    -   [x] Enhanced `BaseApiController::error()` method with error title mapping
    -   [x] Consistent error response structure across all endpoints
    -   [x] Proper HTTP status codes for different error types
    -   [x] Error titles based on status codes (Bad request, Unauthenticated, Forbidden, etc.)

### Location Management System ✅ COMPLETED

#### 11. Cities and Regions Management
-   [x] Create cities table (admin-managed)
-   [x] Create regions table (admin-managed, belongs to city)
-   [x] City model with regions relationship
-   [x] Region model with city, companies, and drivers relationships
-   [x] CityController - Full CRUD (admin only)
-   [x] RegionController - Full CRUD (admin only)
-   [x] CityResource and RegionResource
-   [x] Form requests (CreateCityRequest, UpdateCityRequest, CreateRegionRequest, UpdateRegionRequest)
-   [x] CitySeeder and RegionSeeder with Arabic data
-   [x] Swagger documentation for cities and regions endpoints

#### 12. Company Address and Coordinates
-   [x] Add latitude, longitude, region_id, address_line to companies table
-   [x] Update Company model with region relationship
-   [x] Update CompanyResource to include region information
-   [x] Update form requests to use region_id instead of free text city/region
-   [x] Migrations handle PostgreSQL transaction issues
-   [x] Swagger documentation updated

#### 13. Driver-Region Assignments
-   [x] Create driver_regions pivot table
-   [x] User model with driverRegions relationship
-   [x] Region model with drivers relationship
-   [x] DriverController endpoints for region management
-   [x] Suppliers can assign regions to their drivers
-   [x] Drivers can serve multiple regions
-   [x] Order assignment checks if driver serves pharmacy's region
-   [x] Swagger documentation for driver-region endpoints

#### 14. Cart Items Grouped by Supplier
-   [x] CartResource updated to include itemsBySupplier array
-   [x] Items grouped by supplier_company_id
-   [x] Each group includes supplier, items, subtotal, itemCount
-   [x] Backward compatible (still includes flat items array)
-   [x] Swagger documentation updated

#### 15. Order Date Range Filtering
-   [x] Add from_date and to_date query parameters to orders endpoint
-   [x] Filter orders by created_at date range
-   [x] Swagger documentation updated

### Low Priority

#### 8. Caching (Performance)

-   [ ] Cache product lists
-   [ ] Cache company lists
-   [ ] Cache frequently accessed data
-   [ ] Implement cache invalidation strategies

#### 9. API Resources/Transformers ✅ COMPLETED

-   [x] OrderResource - Transform order data
    -   [x] Includes buyerCompany, supplierCompany, creator, driver, items relationships
    -   [x] Formats dates as ISO strings
    -   [x] Handles nested OrderItemResource
-   [x] OrderItemResource - Transform order item data
    -   [x] Includes product relationship
    -   [x] Formats prices as floats
-   [x] ProductResource - Transform product data
    -   [x] Includes supplierCompany, category, manufacturer relationships
    -   [x] Handles dual pricing (pharmacist_price, customer_price)
    -   [x] Formats prices and stock quantities
-   [x] CategoryResource - Transform category data
    -   [x] Includes all category fields with camelCase properties
    -   [x] Formats dates as ISO strings
-   [x] ManufacturerResource - Transform manufacturer data
    -   [x] Includes all manufacturer fields with camelCase properties
    -   [x] Formats dates as ISO strings
-   [x] CompanyResource - Transform company data
    -   [x] Includes all company fields
    -   [x] Includes categories and manufacturers arrays (when loaded)
    -   [x] Formats dates as ISO strings
-   [x] CartResource - Transform cart data
    -   [x] Includes user, company, items relationships
    -   [x] Includes has_multiple_suppliers flag
    -   [x] Handles nested CartItemResource
-   [x] CartItemResource - Transform cart item data
    -   [x] Includes product and appliedOffer relationships
    -   [x] Formats prices as floats
-   [x] OfferResource - Transform offer data
    -   [x] Includes supplierCompany and products relationships
    -   [x] Includes is_currently_active computed field
    -   [x] Formats dates as ISO strings
-   [x] UserResource - Transform user data (for drivers/profile)
    -   [x] Includes company relationship
    -   [x] Excludes sensitive fields (password, remember_token)
    -   [x] Formats dates as ISO strings
-   [x] StockMovementResource - Transform stock movement data
    -   [x] Includes product, order, user relationships
    -   [x] Formats quantities as integers
-   [x] Consistent API response structure
    -   [x] All controllers updated to use resources
    -   [x] Removed makeHidden() calls (resources handle relationships)
    -   [x] Consistent pagination format across all list endpoints
    -   [x] Resources handle conditional loading with whenLoaded()

#### 10. Testing ⚠️ CRITICAL MISSING

-   [ ] Feature tests for authentication endpoints
-   [ ] Feature tests for order endpoints
-   [ ] Feature tests for product endpoints
-   [ ] Feature tests for company endpoints
-   [ ] Feature tests for cart endpoints
-   [ ] Feature tests for offer endpoints
-   [ ] Unit tests for domain services (`OrderCreationService`, `OrderStateTransitionService`, `StockMovementService`, `CartOfferService`)
-   [ ] Unit tests for models (business logic methods)
-   [ ] Policy tests
-   [ ] Integration tests for critical flows
-   [ ] Test coverage reporting
-   [ ] CI/CD pipeline with automated testing

#### 11. Additional Features ⚠️ SEE "Missing Features & Requirements" SECTION BELOW

-   [ ] User management endpoints (admin) - See Missing Features #18
-   [ ] Enhanced filtering/search - See Missing Features #9
-   [ ] Dashboard/Statistics endpoints - See Missing Features #8
-   [x] ~~Invoice Management API~~ - ✅ COMPLETED (See Completed Features #11)
-   [ ] Product Images & Media - See Missing Features #2
-   [ ] Export Functionality API - See Missing Features #3
-   [ ] Notification Infrastructure - See Missing Features #4
-   [ ] Driver Availability Management - See Missing Features #6
-   [ ] Payment Integration - See Missing Features #7
-   [ ] Real-Time Features - See Missing Features #10
-   [ ] Order Templates - See Missing Features #11
-   [ ] Wishlist Functionality - See Missing Features #12
-   [ ] Reviews & Ratings - See Missing Features #13
-   [ ] Multi-Language Support - See Missing Features #14
-   [ ] Inventory Enhancements - See Missing Features #15
-   [ ] Security Enhancements - See Missing Features #16
-   [ ] Performance Optimizations - See Missing Features #17

---

## 🔮 Future Requests

### Product Enhancements ✅ COMPLETED

1. **Product Category System** ✅

    - [x] Create `categories` table (admin-managed)
    - [x] Add `category_id` foreign key to products table
    - [x] Migrate existing category strings to category_id
    - [x] Update Product model with category relationship
    - [x] Create CategoryController (admin CRUD, public list/show)
    - [x] Add category filtering to product list endpoint (`?category_id=1`)
    - [x] Update Swagger documentation

2. **Product Manufacturer System** ✅

    - [x] Create `manufacturers` table (admin-managed)
    - [x] Add `manufacturer_id` foreign key to products table
    - [x] Update Product model with manufacturer relationship
    - [x] Create ManufacturerController (admin CRUD, public list/show)
    - [x] Add manufacturer filtering to product list endpoint (`?manufacturer_id=1`)
    - [x] Update Swagger documentation

3. **Dual Pricing System** ✅

    - [x] Add `pharmacist_price` field (selling price for pharmacist/buyer)
    - [x] Add `customer_price` field (selling price for end customer)
    - [x] Update existing `price` field handling (migration strategy)
    - [x] Update Product model and validation
    - [x] Update product endpoints to handle both prices
    - [x] Update Swagger documentation

4. **Discount System** ✅
    - [x] Add `discount` field (discount amount/percentage)
    - [x] Add `discount_type` field (enum: 'percentage', 'fixed') - applies to pharmacist price only
    - [x] Update Product model and validation
    - [x] Calculate effective price based on discount (getFinalPharmacistPrice, getFinalCustomerPrice methods)
    - [x] Product discount targets pharmacist price only; customer price returned as-is
    - [x] pharmacistPriceAfterDiscount in API when discount exists
    - [x] Removed legacy `price` field; pharmacist_price required
    - [x] Update product endpoints
    - [x] Update Swagger documentation

### Offers/Promotions System ✅ COMPLETED

4. **Product Offers Management** ✅
    - [x] Create `offers` table with all required fields
    - [x] Create `offer_products` pivot table
    - [x] Create Offer model with relationships
    - [x] Create OfferController with CRUD endpoints
    - [x] Support offer types (offerType encodes discount; no separate discountType):
        - buy_x_get_y
        - percentage_discount
        - fixed_discount
        - bundle_percentage
        - bundle_fixed
    - [x] end_date optional (omit for unlimited offers)
    - [x] is_active field for offer enable/disable
    - [x] Offer validation (dates, active status)
    - [x] **All offer types require `quantity_required` field** (minimum quantity to qualify)
    - [x] Authorization (suppliers can only manage their offers)
    - [x] Swagger documentation
    - [x] **Cart Offer Application** ✅
        - [x] Created `CartOfferService` for automatic offer detection and application
        - [x] Offers automatically applied when adding items to cart
        - [x] Best offer selection (lowest total price)
        - [x] Support for product-specific offer overrides via pivot table
        - [x] Offer re-evaluation when cart items are added/updated/removed
        - [x] Bundle offers check all required products in cart
        - [x] Cart items store `applied_offer_id` when offers are applied
        - [x] **Manual Offer Selection** ✅
            - [x] `GET /api/v1/cart/items/{id}/offers` - Get all available offers for cart item
            - [x] `POST /api/v1/cart/items/{id}/apply-offer` - Manually apply specific offer
            - [x] `POST /api/v1/cart/items/{id}/remove-offer` - Remove manual offer, revert to best offer
            - [x] Users can override automatic best offer selection
            - [x] Shows savings and pricing for each available offer

### Cart Management ✅ COMPLETED

5. **Shopping Cart System** ✅

    - [x] Create `carts` table with totals (subtotal, tax_amount, total_amount)
    - [x] Create `cart_items` table
    - [x] Create Cart and CartItem models with relationships
    - [x] Cart management endpoints:
        - [x] `GET /api/v1/cart` - Get current user's cart
        - [x] `POST /api/v1/cart/items` - Add item to cart
        - [x] `PUT /api/v1/cart/items/{id}` - Update cart item quantity
        - [x] `GET /api/v1/cart/items/{id}/offers` - Get available offers for cart item
        - [x] `POST /api/v1/cart/items/{id}/apply-offer` - Apply specific offer to cart item
        - [x] `POST /api/v1/cart/items/{id}/remove-offer` - Remove manual offer selection
        - [x] `DELETE /api/v1/cart/items/{id}` - Remove item from cart
        - [x] `DELETE /api/v1/cart` - Clear entire cart
        - [x] `POST /api/v1/cart/checkout` - Convert cart to order(s)
        - [x] `POST /api/v1/cart/checkout-supplier` - Checkout items for specific supplier (other items remain in cart)
    - [x] Cart calculations (subtotal, tax, total)
    - [x] Apply product discounts to cart (uses getFinalPharmacistPrice)
    - [x] **Supplier-Specific Checkout** ✅
        - [x] Checkout only items from a specific supplier
        - [x] Other items remain in cart
        - [x] Cart totals automatically recalculated after checkout
        - [x] Returns order details and counts of checked out/remaining items
    - [x] **Automatic offer application** ✅
        - [x] Offers automatically detected and applied when adding/updating cart items
        - [x] Best offer selected based on lowest total price
        - [x] Offers re-evaluated when quantities change or items are removed
        - [x] Bundle offers require all products in cart with required quantities
    - [x] Handle multi-supplier carts (see decision below)
    - [x] Authorization (users can only manage their own cart)
    - [x] Swagger documentation

6. **Multi-Supplier Order Handling** ✅ **IMPLEMENTED - Option A**
    - **✅ Option A: Backend handles splitting** - IMPLEMENTED
        - [x] Backend automatically splits cart into multiple orders by supplier
        - [x] Returns array of created orders
        - [x] Single API call for checkout
        - [x] Atomic transaction ensures all orders created or none
        - [x] Cart cleared after successful checkout
    - **Decision**: Option A implemented - Better data integrity, atomic operations, cleaner API

### Driver Management ✅ COMPLETED

7. **Driver Management for Suppliers** ✅
    - [x] Driver role handling (already exists in UserRole constants)
    - [x] Create driver management endpoints:
        - [x] `GET /api/v1/drivers` - List drivers (supplier only, their company)
        - [x] `POST /api/v1/drivers` - Create driver (supplier/admin only)
        - [x] `GET /api/v1/drivers/{id}` - Get driver details
        - [x] `PUT /api/v1/drivers/{id}` - Update driver
        - [x] `DELETE /api/v1/drivers/{id}` - Delete driver
        - [x] `GET /api/v1/drivers/{id}/orders` - Get driver's assigned orders
        - [x] `POST /api/v1/orders/{order}/assign-driver` - Assign driver to order
    - [x] Driver assignment to orders
    - [x] Authorization (suppliers manage their drivers)
    - [x] Update Order model to include driver relationship
    - [x] Swagger documentation

### Profile Management ✅ COMPLETED

8. **User Profile Management** ✅
    - [x] `GET /api/v1/profile` - Get current user profile
    - [x] `PUT /api/v1/profile` - Update current user profile
    - [x] `PUT /api/v1/profile/password` - Change password
    - [x] `PUT /api/v1/profile/company` - Update company information (if user is company admin)
    - [x] Profile validation
    - [x] Password change validation (current password required)
    - [x] Authorization (users can only update their own profile)
    - [x] Swagger documentation

### Categories & Manufacturers Management ✅ COMPLETED

9. **Admin-Managed Categories and Manufacturers** ✅
    - [x] Create `categories` table with name, slug, description, sort_order, is_active
    - [x] Create `manufacturers` table with name, slug, description, country, website, email, phone, sort_order, is_active
    - [x] Update products table to use `category_id` and `manufacturer_id` foreign keys
    - [x] Migration to convert existing category strings to category_id
    - [x] Create Category and Manufacturer models with relationships
    - [x] Create CategoryController with CRUD (admin-only for create/update/delete, public list/show)
    - [x] Create ManufacturerController with CRUD (admin-only for create/update/delete, public list/show)
    - [x] Update ProductController to use category_id and manufacturer_id
    - [x] Update Product form requests to validate category_id and manufacturer_id (must exist)
    - [x] Update ProductResource to include category and manufacturer objects
    - [x] Create CategorySeeder and ManufacturerSeeder
    - [x] Update ProductSeeder to use category_id and manufacturer_id
    - [x] Swagger documentation for category and manufacturer endpoints
    - [x] Products search includes category and manufacturer names

10. **Auto-Sync Supplier Categories and Manufacturers** ✅
    - [x] Create `supplier_categories` pivot table
    - [x] Create `supplier_manufacturers` pivot table
    - [x] Add categories() and manufacturers() relationships to Company model
    - [x] Create ProductObserver to auto-sync when products change
    - [x] Auto-sync on product create, update, delete, restore
    - [x] Only syncs from active products with category_id/manufacturer_id
    - [x] Handles supplier changes (syncs both old and new suppliers)
    - [x] Suppliers cannot manually control categories/manufacturers (ensures data reliability)
    - [x] Update CompanyController to filter by category_id and manufacturer_id
    - [x] Update CompanyController to search by category/manufacturer names
    - [x] Update CompanyResource to include categories and manufacturers arrays
    - [x] Create sync command: `php artisan suppliers:sync-categories-manufacturers`
    - [x] Swagger documentation updated for company filtering/search

### Invoice Management ✅ COMPLETED

11. **Invoice Management System** ✅
    - [x] Create `invoices` table with all required fields
    - [x] Create Invoice model with relationships (order, buyerCompany, supplierCompany)
    - [x] Invoice status tracking (generated, sent, paid, overdue, cancelled)
    - [x] Payment status management (pending, paid, partial, overdue, refunded)
    - [x] Update GenerateInvoiceJob to create Invoice records in database
    - [x] Automatic invoice generation when orders are accepted
    - [x] OrderSeeder creates invoices for accepted orders synchronously
    - [x] Invoice management endpoints:
        - [x] `GET /api/v1/invoices` - List invoices with filtering
        - [x] `GET /api/v1/invoices/{id}` - Get invoice details
        - [x] `GET /api/v1/orders/{order_id}/invoice` - Get invoice for specific order
        - [x] `GET /api/v1/invoices/{id}/download` - Download invoice file
        - [x] `POST /api/v1/invoices/{id}/send` - Mark invoice as sent (suppliers)
        - [x] `POST /api/v1/invoices/{id}/pay` - Mark invoice as paid (with optional amount)
    - [x] Role-based access control (buyers see their invoices, suppliers see theirs)
    - [x] Invoice filtering (by order, status, payment_status, date range)
    - [x] Invoice helper methods (isPaid, isOverdue, markAsSent, markAsPaid)
    - [x] InvoiceResource for API responses
    - [x] Order model includes invoice relationship
    - [x] OrderResource optionally includes invoice
    - [x] Swagger documentation

---

## 📌 Notes & Decisions

### Architecture Decisions

-   **Modular Monolith**: Using domain-driven design within a single application
-   **API-First**: All endpoints designed for API consumption
-   **Token-Based Auth**: Using Laravel Sanctum for authentication
-   **Soft Deletes**: Critical data (orders, invoices, stock movements) use soft deletes
-   **Transactional Operations**: All stock and order changes are transactional
-   **Audit Trail**: All state changes are logged

### Database Decisions

-   **PostgreSQL**: Using Neon Postgres for database
-   **Migrations**: Disabled transactions for migrations due to Neon connection pooling
-   **Seeders**: Use unpooled connection for reliable transaction handling
-   **Indexes**: Added indexes on frequently queried columns
-   **Connection Strategy**:
    -   Pooled connection (`-pooler`) for API requests (high concurrency)
    -   Unpooled connection (direct) for seeders and migrations (reliable transactions)
-   **Transaction Handling**: Global cleanup middleware handles failed transactions automatically

### Cache Decisions

-   **Current**: File-based cache (eliminates transaction conflicts)
-   **Production Ready**: Redis configuration available for scaling
-   **Strategy**: File cache for development, Redis for production high-traffic scenarios

### API Design Decisions

-   **Versioning**: All APIs under `/api/v1`
-   **Response Format**: Standardized JSON with `success`, `data`, `error` fields
-   **Pagination**: All list endpoints are paginated
-   **Rate Limiting**: Login endpoint limited to 5 requests/minute

### Code Quality

-   **Thin Controllers**: Controllers only validate and call services
-   **Business Logic in Domain**: Not in controllers or models
-   **Form Request Validation**: All input validated via Form Request classes
-   **Policy-Based Authorization**: All actions protected by policies

### Known Issues

-   **Neon Connection Pooling**: Pooled connections don't handle complex transactions well during seeding. Solution: Use separate unpooled connection configuration for seeders.
-   ~~**Transaction Errors**: Fixed with transaction cleanup middleware and file-based cache.~~

### Pending Decisions

-   ~~**Multi-Supplier Cart Checkout**: Need to decide between backend automatic splitting vs frontend bulk order creation. Recommendation: Backend handles splitting for better data integrity and atomic operations.~~ ✅ **DECIDED & IMPLEMENTED**: Option A (Backend handles splitting) - Implemented in CartController checkout method
-   ~~**Categories and Manufacturers Management**: Should suppliers control their categories/manufacturers or auto-sync from products?~~ ✅ **DECIDED & IMPLEMENTED**: Auto-sync from products (suppliers cannot manually control) - Ensures data reliability and consistency. Categories and manufacturers are admin-managed, and supplier relationships are automatically derived from their active products via ProductObserver.

### Supplier & Pricing Decisions (2026-04-03)

-   ✅ **Wholesale catalog visibility**: Wholesale-only suppliers are **hidden** from pharmacist buyers. Only salesmen and admins can see them.
-   ✅ **Salesman pricing fallback**: If a salesman doesn't meet wholesale min qty on a line item, they get **retail price** (not rejected).
-   ✅ **Custom price lists**: Implemented via `price_lists` table. Highest priority in price resolution chain.
-   ✅ **Credit terms**: `buyer_supplier_relationships` table with payment_terms (cash/net_15/net_30/net_60) and credit_limit.
-   ✅ **Salesman dual model**: Salesmen can be supplier-associated (like drivers) OR independent (own buyer company). Both supported.
-   ✅ **Min qty enforcement**: Per line item, not per order total.
-   ✅ **Price resolution order**: Custom price list → wholesale tier → retail → product discount → promotional offers.

### Technical Debt

-   `OrderStatus` vs `OrderState` inconsistency (shipped→cancelled) — pre-existing, not introduced in this change
-   `App\Domain\Users\UserRole` duplicates `App\Constants\UserRole` — should consolidate to single source

### Recent Improvements

-   **Phase 1/2 Comprehensive Audit — 11 Issues Fixed (2026-04-03)**:
    - **InvoiceController** (HIGH): Fixed supplier-associated salesman access — was checking `buyer_company_id === company_id` but supplier salesmen have supplier `company_id`. Added `canAccessInvoice()` and `canAccessOrder()` helpers with proper branch logic for independent vs supplier-associated salesmen.
    - **CompanyController::store** (HIGH): Added missing `$this->authorize('create', Company::class)` — previously any authenticated user could create companies despite admin-only policy.
    - **StockController** (HIGH): Added missing `use App\Http\Resources\StockMovementResource` import.
    - **OfferController** (MEDIUM): Added `isSupplier()` guard to `update()` and `destroy()` — supplier-associated salesmen shared supplier `company_id` and could modify offers.
    - **UserSeeder** (HIGH): Fixed hardcoded `company_id => 4` and `company_id => 1` for supplier users. Now uses `$suppliers->first()` and `$suppliers->skip(1)->first()` for portable IDs.
    - **OrderSeeder** (HIGH): Changed `User::where('role', 'buyer')` to `User::whereIn('role', UserRole::buyerRoles())`. Added salesman-placed order with `placed_by_salesman_id`, `payment_method` (NET_30), `payment_due_date`, and `pricing_type` (WHOLESALE). Order items now update `pricing_type` post-creation.
    - **CreateOrderRequest**: Added `payment_method` (validated against `PaymentTerms::all()`) and `placed_by_salesman_id` (validated `exists:users,id`).
    - **AddCartItemRequest / UpdateCartItemRequest**: Added `pricing_type` validation (`Rule::in(PricingType::all())`).
    - **UpdateProductRequest**: Added `required_with:wholesale_price` to `wholesale_min_qty` (aligned with CreateProductRequest).
    - **OrderController**: Added `salesman` to eager-loaded relations in both `index()` and `show()` — `OrderResource` wraps salesman in `whenLoaded()`.
    - **EnumsController Swagger**: Added `supplierClassification`, `buyerClassification`, `pricingType`, `paymentTerms` to OpenAPI `type` query param enum.
-   **Supplier Classifications & Multi-Tier Pricing — Phase 5: Salesman Features (2026-04-03)**:
    - Created `SalesmanController` with 10 endpoints:
      - `GET /salesmen` — list salesmen (admin sees all, supplier sees their salesmen, salesman sees self)
      - `GET /salesmen/{id}` — detailed profile with regions, clients, order counts, salesman type
      - `GET /salesmen/{id}/regions` — list assigned regions
      - `POST /salesmen/{id}/regions` — assign regions (sync, replaces existing)
      - `DELETE /salesmen/{id}/regions/{regionId}` — remove specific region
      - `GET /salesmen/{id}/clients` — list pharmacy clients with active/inactive filter
      - `POST /salesmen/{id}/clients` — assign pharmacy as client (validates buyer_classification = pharmacy, prevents duplicates)
      - `DELETE /salesmen/{id}/clients/{clientId}` — deactivate client assignment (soft removal)
      - `GET /salesmen/{id}/orders` — list orders placed by salesman on behalf of buyers
      - `POST /salesmen/{id}/place-order` — on-behalf ordering with PricingService integration, client validation, credit terms auto-calculation
    - On-behalf ordering flow: validates client assignment → resolves pricing via PricingService for buyer context → creates order with `placed_by_salesman_id` → auto-sets `payment_due_date` from buyer-supplier relationship
    - Created `SalesmanClientResource` API resource
    - Created 3 Form Requests: `AssignSalesmanRegionsRequest`, `AssignSalesmanClientRequest`, `PlaceOrderOnBehalfRequest`
    - Authorization: `canAccessSalesman()` (view) and `canManageSalesman()` (edit) helper methods — suppliers manage their salesmen, admin manages all
    - **Files created**: 1 controller, 1 resource, 3 form requests
    - **Files modified**: `routes/api.php`
-   **Supplier Classifications & Multi-Tier Pricing — Phase 4: Buyer-Supplier Relationships & Price Lists API (2026-04-03)**:
    - Created `BuyerSupplierRelationshipController` — full CRUD + credit management:
      - `GET /buyer-supplier-relationships` — list with role-based filtering (admin/supplier/buyer/salesman)
      - `POST /buyer-supplier-relationships` — create with company validation + duplicate prevention
      - `GET/PUT/DELETE /buyer-supplier-relationships/{id}` — standard CRUD with policy authorization
      - `POST /{id}/approve` — approve a pending relationship
      - `POST /{id}/reject` — reject/revoke approval
      - `POST /{id}/adjust-credit` — adjust outstanding balance with audit logging (reason required)
      - `GET /{id}/check-credit` — check credit availability for a given amount
    - Created `PriceListController` — custom per-buyer pricing CRUD:
      - `GET /price-lists` — list with filtering (buyer, product, active, currently_valid)
      - `POST /price-lists` — create with product ownership validation + duplicate prevention
      - `GET/PUT/DELETE /price-lists/{id}` — standard CRUD with policy authorization
      - `GET /price-lists/for-buyer/{buyerCompanyId}` — get all currently valid prices for a buyer
    - Created API Resources: `BuyerSupplierRelationshipResource` (includes `availableCredit` computed field), `PriceListResource` (includes `isCurrentlyValid`)
    - Created Form Requests: `Create/UpdateBuyerSupplierRelationshipRequest`, `Create/UpdatePriceListRequest`
    - Created Policies: `BuyerSupplierRelationshipPolicy`, `PriceListPolicy` — suppliers manage, buyers view
    - Registered policies in `AppServiceProvider`
    - Added 15 new API routes (9 for relationships, 6 for price lists)
    - Salesman handling: supplier-associated salesmen see their supplier's data; independent salesmen see their buyer data
    - **Files created**: 2 controllers, 2 resources, 4 form requests, 2 policies
    - **Files modified**: `routes/api.php`, `AppServiceProvider`
-   **Supplier Classifications & Multi-Tier Pricing — Phase 3: Cart/Order/Catalog Integration (2026-04-03)**:
    - Integrated `PricingService` into `CartController` — all add/update/remove operations now resolve prices based on buyer type, supplier classification, quantity (wholesale eligibility), and custom price lists
    - `addItem()`: resolves tier price via PricingService, checks catalog visibility (`canBuyerSeeSupplier`), sets `pricing_type` on cart items
    - `updateItem()`: re-resolves price when quantity changes (qty may cross wholesale threshold)
    - `removeItem()`: re-evaluates all remaining items with PricingService
    - `removeOffer()` / `applyOffer()` / `getAvailableOffers()`: use tier-resolved base price instead of hardcoded pharmacist price
    - `checkout()` / `checkoutSupplier()`: passes `pricing_type` through to order items
    - Integrated `PricingService` into `OrderController::store()` — auto-resolves price when `unit_price` not provided
    - Updated `OrderCreationService` to accept and store `pricing_type` on order items
    - Updated `CartOfferService` — all public methods (`findBestOffer`, `getAvailableOffers`, `applyOffer`) now accept optional `$basePrice` parameter; offer discounts apply on top of tier-resolved price
    - Added **catalog visibility** to `ProductController::index()` — wholesale-only suppliers hidden from pharmacy buyers
    - Fixed `CartItemResource::baseUnitPrice` to use correct tier price based on `pricing_type` (wholesale → `getFinalWholesalePrice()`, retail → `getFinalPharmacistPrice()`, custom → stored price)
    - Added `pricing_type` validation to `CreateOrderRequest` (items.*.pricing_type)
    - Updated Swagger descriptions in `OrderController` to reflect PricingService integration
    - **Pricing resolution flow**: PricingService resolves tier price → CartOfferService checks promotional offers → best price wins
    - **Files modified**: CartController, OrderController, ProductController, CartOfferService, OrderCreationService, CartItemResource, CreateOrderRequest
-   **Supplier Classifications & Multi-Tier Pricing — Phase 2: PricingService & Salesman Integration (2026-04-03)**:
    - Created `PricingService` (`App\Domain\Pricing\PricingService`) — central price resolution engine
      - `resolve()`: single product price resolution (custom → wholesale → retail → discount)
      - `resolveMany()`: batch resolution with preloaded price lists for performance
      - `isWholesaleEligible()`: checks supplier classification + buyer classification
      - `canBuyerSeeSupplier()`: catalog visibility (wholesale-only hidden from pharmacies)
      - `getDisplayPrice()`: formatted price info for API responses
    - Created `PriceResult` DTO (`App\Domain\Pricing\PriceResult`) — immutable price result
    - Fixed salesman role across all controllers:
      - CartController: `isBuyer()` → `canBuy()` in `addItem()`, `checkout()`, `checkoutSupplier()`
      - OrderController: `isBuyer()` → `canBuy()` in `store()`; added salesman order visibility logic in `index()`
      - InvoiceController: added `isSalesman()` to all authorization checks (index, show, getByOrder, download, markAsPaid)
      - ProfileController: added salesman to company update permission check
      - AuthController: added 'salesman' to Swagger role enum
      - EnumsController: updated Swagger role description
      - SendOrderNotificationJob: buyer notifications now include salesman role users
    - Updated `app/Domain/Pricing/README.md` to reflect implemented services
-   **Supplier Classifications & Multi-Tier Pricing — Phase 1: Data Model (2026-04-03)**:
    - Added supplier classification system: `wholesale`, `retail`, `hybrid` (on companies table)
    - Added buyer classification system: `pharmacy`, `salesman` (on companies table)
    - Added `salesman` user role (can be supplier-associated or independent)
    - Added three-tier product pricing: `wholesale_price` + `wholesale_min_qty` alongside existing `pharmacist_price` and `customer_price`
    - Added `pricing_type` tracking on `cart_items` and `order_items` (wholesale/retail/custom)
    - Created `price_lists` table for custom per-buyer negotiated pricing
    - Created `buyer_supplier_relationships` table with payment terms (cash/net_15/net_30/net_60) and credit limits
    - Created `salesman_regions` pivot table (similar to driver_regions)
    - Created `salesman_clients` table for salesman-pharmacy assignments (supports both supplier-associated and independent salesmen)
    - Added `payment_method`, `payment_due_date`, `placed_by_salesman_id` to orders table
    - Created 4 new constant classes: `SupplierClassification`, `BuyerClassification`, `PricingType`, `PaymentTerms`
    - Updated `UserRole` with `SALESMAN` + helper methods (`supplierRoles()`, `buyerRoles()`)
    - Updated `CompanyType` with classification helper methods
    - Updated Company model: new fields, classification checks (`isWholesale()`, `isRetail()`, `isHybrid()`, `isPharmacy()`, `isSalesmanCompany()`, `canOfferWholesaleTo()`)
    - Updated Product model: wholesale pricing fields, `getEffectiveWholesalePrice()`, `getFinalWholesalePrice()`, `qualifiesForWholesale()`, `priceLists()` relationship
    - Updated User model: salesman support (`isSalesman()`, `isSupplierSalesman()`, `isIndependentSalesman()`, `canBuy()`, `salesmanRegions()`, `salesmanClients()`, `salesmanOrders()`)
    - Updated Order model: `placed_by_salesman_id`, `payment_method`, `payment_due_date`, `salesman()` relationship, `isPlacedBySalesman()`
    - Updated CartItem and OrderItem models: `pricing_type` field
    - Created 3 new models: `PriceList`, `BuyerSupplierRelationship`, `SalesmanClient`
    - Updated EnumsController to expose new constants (supplierClassification, buyerClassification, pricingType, paymentTerms)
    - Created new domain: `app/Domain/Pricing/README.md`
    - Updated domain READMEs: Companies, Products, Orders, Deliveries
    - Backfill strategy: existing suppliers → `retail`, existing buyers → `pharmacy`
    - **Key decisions**: Wholesale-only suppliers hidden from pharmacists; salesman falls back to retail if min qty not met; per-item min qty enforcement; custom price lists have highest priority
    - **Seeders updated**:
      - CompanySeeder: 4 suppliers (1 retail, 1 hybrid, 1 wholesale, 1 retail) + 4 buyers (3 pharmacies, 1 salesman company)
      - UserSeeder: added 2 supplier-associated salesmen + 1 independent salesman
      - ProductSeeder: added wholesale_price + wholesale_min_qty to hybrid/wholesale supplier products
      - BuyerSupplierRelationshipSeeder (NEW): credit terms, price lists, salesman-pharmacy assignments
      - DatabaseSeeder: updated call order to include new seeder
-   **Bundle Offers Per-Product Quantity (2026-02-27)**:
    - Bundle offers now require `products` array: `[{ product_id, quantity }]` - each product has its own required quantity
    - `product_ids` still supported for non-bundle offers (uses offer-level quantity_required)
    - OfferResource returns `quantityRequired` per product in products array
    - OfferSeeder updated to use pivot quantity_required for all offers
-   **Enums Endpoint (2026-02-27)**:
    - Added `GET /api/v1/enums` - returns all system enums (offerType, orderStatus, userRole, companyType, stockMovementType, discountType)
    - Optional `?type=offerType` to return a single enum
    - Useful for frontend dropdowns, form options, validation
-   **Cart & Offer Enhancements (2026-02-27)**:
    - Cart items now return `baseUnitPrice` and `baseTotalPrice` (prices before offer) for frontend to show price changes (e.g. strikethrough original, show discounted)
    - Swagger docs improved: CartItem and Offer schemas, Cart/Offer tag descriptions
    - Documented offer system: one offer per product, best offer auto-selected, bundle requirements
    - Documented remove-item re-evaluation of remaining offers (bundle eligibility)
    - OfferController create: fixed offer_type enum (bundle_percentage, bundle_fixed instead of bundle)
    - Checkout description: cart item prices (offer-adjusted) used for order creation
-   **Codebase Rules (2026-02-27)**: Updated `.cursor/rules/codebase_rules.mdc` with documentation hub requirements:
    - PROGRESS.md is the main brain: must document every change, mistakes, and current system state
    - Added "Related Documentation" section at top of PROGRESS.md linking to all system docs
    - Queue-related changes → document in QUEUE_WORKERS.md
    - Deployment-related changes → document in DEPLOYMENT.md
    - Added rule: no changes without updating relevant docs
-   **Transaction Handling**: Implemented global transaction cleanup middleware to handle PostgreSQL connection pooling issues
-   **Error Handling**: Improved API error responses for unauthenticated requests
-   **Cache Strategy**: Configured file-based cache (eliminates transaction conflicts), Redis ready for production
-   **Code Quality**: Cleaned up commented code, standardized imports, fixed constant usage
-   **Documentation**: Consolidated documentation files for better maintainability
-   **Domain Documentation**: Created comprehensive READMEs for all 5 domains (Orders, Inventory, Products, Companies, Deliveries)
-   **Background Jobs**: Implemented 4 background jobs with retry logic and proper error handling
-   **Queue Workers**: Created comprehensive queue workers setup guide (QUEUE_WORKERS.md)
-   **Swagger Documentation**: Updated all product endpoints to reflect new fields (category, dual pricing, discounts)
-   **Swagger Documentation**: Updated all order endpoints to document multi-supplier order handling
-   **Seeders**: Updated ProductSeeder with all new fields, created OfferSeeder, made all seeders idempotent
-   **Seeders**: Enhanced UserSeeder with multiple drivers per supplier
-   **Categories & Manufacturers**: Implemented admin-managed categories and manufacturers system
    -   Created Category and Manufacturer models with CRUD endpoints
    -   Products now use foreign keys (category_id, manufacturer_id) instead of strings
    -   Auto-sync supplier categories/manufacturers from products via ProductObserver
    -   Suppliers cannot manually control their categories/manufacturers (ensures data reliability)
    -   Company filtering/search by category_id and manufacturer_id
    -   Created sync command for manual syncing: `php artisan suppliers:sync-categories-manufacturers`
-   **Location Management System**: Implemented hierarchical cities and regions system
    -   Created City and Region models with admin-managed CRUD endpoints
    -   Companies use region_id (foreign key) instead of free text city/region
    -   Companies include address, coordinates (latitude, longitude), and address_line
    -   Driver-region assignments allow suppliers to assign regions to drivers
    -   Order assignment considers pharmacy region for better driver matching
    -   CitySeeder and RegionSeeder with Arabic data (Damascus with Mazzeh, Malki, Rawda, etc.)
-   **Cart Enhancements**: Cart items now grouped by supplier in API response
    -   CartResource includes itemsBySupplier array (matches checkout behavior)
    -   Each supplier group includes supplier info, items, subtotal, and itemCount
    -   Backward compatible (still includes flat items array)
-   **Order Filtering**: Added date range filtering for orders
    -   Filter orders by from_date and to_date query parameters
    -   Useful for reporting and analytics
    -   Companies include address, coordinates (latitude, longitude), and address_line
    -   Driver-region assignments allow suppliers to assign regions to drivers
    -   Order assignment considers pharmacy region for better driver matching
    -   CitySeeder and RegionSeeder with Arabic data (Damascus with Mazzeh, Malki, Rawda, etc.)
-   **Cart Enhancements**: Cart items now grouped by supplier in API response
    -   CartResource includes itemsBySupplier array (matches checkout behavior)
    -   Each supplier group includes supplier info, items, subtotal, and itemCount
    -   Backward compatible (still includes flat items array)
-   **Order Filtering**: Added date range filtering for orders
    -   Filter orders by from_date and to_date query parameters
    -   Useful for reporting and analytics

---

## 🚀 Future Recommendations & Enhancements

### High Priority Recommendations

1. **Geospatial Queries & Route Optimization**
   - Implement distance calculations between companies using coordinates
   - Add endpoint to find nearest suppliers to a pharmacy
   - Implement route optimization algorithm for multi-order deliveries
   - Add delivery time estimation based on distance and traffic
   - Consider using PostGIS extension for advanced geospatial queries

2. **Automatic Driver Assignment**
   - Auto-assign drivers based on pharmacy region
   - Load balancing across drivers (distribute orders evenly)
   - Driver availability tracking (active/inactive, on-duty/off-duty)
   - Driver capacity management (max orders per driver)
   - Smart assignment considering driver's current location and route

3. **Real-Time Delivery Tracking**
   - GPS tracking integration for drivers
   - Real-time location updates during delivery
   - Delivery status updates with location stamps
   - Estimated delivery time calculations
   - Delivery proof (signatures, photos)

4. **Advanced Search & Filtering**
   - Full-text search for products, companies, orders
   - Elasticsearch integration for better search performance
   - Advanced filtering combinations (multiple categories, manufacturers, regions)
   - Search suggestions and autocomplete
   - Saved search filters for users

5. **Analytics & Reporting**
   - Dashboard for suppliers (sales, top products, regions served)
   - Dashboard for buyers (purchase history, spending trends)
   - Admin dashboard (system-wide statistics)
   - Export reports (CSV, PDF, Excel)
   - Order analytics (by region, by supplier, by time period)
   - Driver performance metrics

### Medium Priority Recommendations

6. **Notification System**
   - Email notifications for order status changes
   - SMS notifications for critical updates
   - Push notifications for mobile apps
   - Notification preferences per user/company
   - Notification history and read status

7. **Payment Integration**
   - Payment gateway integration (Stripe, PayPal, etc.)
   - Multiple payment methods (credit card, bank transfer, cash on delivery)
   - Payment terms management (net 30, net 60, etc.)
   - Invoice generation and management
   - Payment tracking and reconciliation

8. **Inventory Management Enhancements**
   - Low stock alerts with customizable thresholds
   - Bulk stock operations
   - Stock transfer between suppliers
   - Expiry date tracking for products
   - Batch/lot number tracking

9. **Multi-Language Support**
   - Arabic and English language support
   - Dynamic language switching
   - Translated product names and descriptions
   - Localized date/time formats
   - RTL (Right-to-Left) layout support

10. **Mobile App API Enhancements**
    - Optimized endpoints for mobile consumption
    - Image upload for products and companies
    - Offline mode support (sync when online)
    - Push notification endpoints
    - Mobile-specific authentication (biometric, PIN)

### Low Priority Recommendations

11. **Advanced Features**
    - Product reviews and ratings
    - Company ratings and reviews
    - Wishlist functionality
    - Product comparison
    - Order templates (frequently ordered items)
    - Subscription management for suppliers
    - Loyalty program integration
    - Referral system

12. **Performance Optimizations**
    - Redis caching for frequently accessed data
    - Database query optimization
    - API response caching
    - CDN integration for static assets
    - Image optimization and lazy loading
    - Database indexing optimization

13. **Security Enhancements**
    - Two-factor authentication (2FA)
    - API rate limiting per user/company
    - IP whitelisting for admin endpoints
    - Audit logging for sensitive operations
    - Data encryption at rest
    - Regular security audits

14. **Testing & Quality Assurance**
    - Unit tests for domain services
    - Integration tests for API endpoints
    - End-to-end tests for critical flows
    - Performance testing
    - Load testing for high-traffic scenarios
    - Automated testing in CI/CD pipeline

15. **Documentation Improvements**
    - API versioning strategy (v2 planning)
    - Postman collection for API testing
    - Video tutorials for common operations
    - Developer onboarding guide
    - Architecture decision records (ADRs)

---

## ❌ Missing Features & Requirements

This section documents features and functionality that are currently missing from the system but would enhance the platform's capabilities and user experience.

### 🔴 Critical Missing Features

These features are essential for core functionality and should be prioritized for implementation.

#### 1. Invoice Management API ✅ COMPLETED
**Status**: Fully implemented with API endpoints and database tracking
- [x] `GET /api/v1/invoices` - List invoices with filtering
- [x] `GET /api/v1/invoices/{id}` - Get invoice details
- [x] `GET /api/v1/invoices/{id}/download` - Download invoice file
- [x] `GET /api/v1/orders/{id}/invoice` - Get invoice for specific order
- [x] `POST /api/v1/invoices/{id}/send` - Mark invoice as sent
- [x] `POST /api/v1/invoices/{id}/pay` - Mark invoice as paid
- [x] Invoice status tracking (generated, sent, paid, overdue, cancelled)
- [x] Invoice payment status management (pending, paid, partial, overdue, refunded)
- [x] Invoice reference storage in database (`invoices` table)
- [x] Automatic invoice generation when orders are accepted
- [x] OrderSeeder creates invoices for accepted orders

#### 2. Product Images & Media Management
**Status**: No image support currently
- [ ] Image upload endpoints for products
- [ ] Multiple images per product support
- [ ] Image storage configuration (S3/local)
- [ ] Image optimization and resizing
- [ ] Company logo upload
- [ ] Product image management (add/remove/reorder)
- [ ] Image CDN integration
- [ ] Migration to add `image_url` or `images` JSON field to products table

#### 3. Export Functionality API
**Status**: `ExportOrdersJob` exists but no API endpoints
- [ ] `POST /api/v1/exports/orders` - Trigger order export
- [ ] `GET /api/v1/exports` - List export history
- [ ] `GET /api/v1/exports/{id}` - Get export status
- [ ] `GET /api/v1/exports/{id}/download` - Download exported file
- [ ] Export format options (CSV, JSON, Excel)
- [ ] Export filtering (date range, status, company)
- [ ] Background export processing with status updates

#### 4. Notification System Infrastructure
**Status**: Jobs exist but infrastructure incomplete
- [ ] Email service configuration (SMTP/SendGrid/Mailgun)
- [ ] SMS integration (Twilio, etc.)
- [ ] Push notification service (Firebase, OneSignal)
- [ ] Notification preferences per user/company
- [ ] Notification history table and endpoints
- [ ] Read/unread status tracking
- [ ] In-app notification system
- [ ] Notification templates management
- [ ] Notification delivery status tracking

#### 5. Testing Suite
**Status**: PHPUnit configured, only example tests exist
- [ ] Feature tests for authentication endpoints
- [ ] Feature tests for order endpoints
- [ ] Feature tests for product endpoints
- [ ] Feature tests for company endpoints
- [ ] Feature tests for cart endpoints
- [ ] Feature tests for offer endpoints
- [ ] Unit tests for domain services (`OrderCreationService`, `OrderStateTransitionService`, `StockMovementService`, `CartOfferService`)
- [ ] Unit tests for models (business logic methods)
- [ ] Policy tests (authorization rules)
- [ ] Integration tests for critical flows (order creation, checkout, stock management)
- [ ] Test coverage reporting
- [ ] CI/CD pipeline with automated testing

### 🟠 High Priority Missing Features

These features significantly enhance user experience and operational efficiency.

#### 6. Driver Availability Management
**Status**: Basic driver management exists, but no availability tracking
- [ ] Driver status management (available, busy, off-duty, unavailable)
- [ ] Driver capacity limits (max orders per driver)
- [ ] Automatic driver assignment based on availability and region
- [ ] Load balancing across drivers
- [ ] Driver location tracking (current location updates)
- [ ] Driver schedule management (working hours, days off)
- [ ] Driver performance metrics (delivery time, completion rate)
- [ ] Endpoints: `PUT /api/v1/drivers/{id}/status`, `GET /api/v1/drivers/available`

#### 7. Payment Integration
**Status**: No payment system implemented
- [ ] Payment gateway integration (Stripe, PayPal, local payment providers)
- [ ] Multiple payment methods support
- [ ] Payment terms management (net 30, net 60, cash on delivery)
- [ ] Payment tracking and status
- [ ] Payment reconciliation system
- [ ] Payment history endpoints
- [ ] Refund processing
- [ ] Payment webhooks handling
- [ ] Invoice-to-payment linking

#### 8. Analytics & Dashboard Endpoints
**Status**: No analytics endpoints exist
- [ ] Supplier dashboard endpoints:
  - [ ] Sales statistics (total sales, revenue, orders count)
  - [ ] Top products by sales
  - [ ] Regions served statistics
  - [ ] Order trends (daily/weekly/monthly)
  - [ ] Customer statistics
- [ ] Buyer dashboard endpoints:
  - [ ] Purchase history and trends
  - [ ] Spending analytics
  - [ ] Favorite suppliers/products
  - [ ] Order frequency analysis
- [ ] Admin dashboard endpoints:
  - [ ] System-wide statistics
  - [ ] User activity metrics
  - [ ] Platform health metrics
  - [ ] Revenue analytics
- [ ] Driver performance metrics endpoints
- [ ] Export analytics reports

#### 9. Advanced Search & Filtering
**Status**: Basic search exists, but limited
- [ ] Full-text search implementation (PostgreSQL full-text search or Elasticsearch)
- [ ] Elasticsearch integration for better performance
- [ ] Search autocomplete/suggestions
- [ ] Multi-field search (name, SKU, description, category, manufacturer)
- [ ] Advanced filtering combinations (multiple categories, manufacturers, regions, price ranges)
- [ ] Saved search filters per user
- [ ] Search history
- [ ] Search ranking and relevance scoring

#### 10. Real-Time Features
**Status**: No real-time capabilities
- [ ] WebSocket support (Laravel Echo, Pusher, Socket.io)
- [ ] Real-time order status updates
- [ ] Real-time delivery tracking
- [ ] Live notifications
- [ ] Real-time inventory updates
- [ ] Real-time chat/messaging (optional)
- [ ] Presence system (online/offline users)

### 🟡 Medium Priority Missing Features

These features add value but are not critical for core operations.

#### 11. Order Templates
**Status**: No template system
- [ ] Save frequently ordered items as templates
- [ ] Template management endpoints (CRUD)
- [ ] Quick reorder from templates
- [ ] Template sharing between company users
- [ ] Template categories/tags

#### 12. Wishlist Functionality
**Status**: No wishlist feature
- [ ] Wishlist endpoints (`GET /api/v1/wishlist`, `POST /api/v1/wishlist/items`, etc.)
- [ ] Add/remove products from wishlist
- [ ] Move wishlist items to cart
- [ ] Share wishlist with other users
- [ ] Wishlist notifications (price drops, stock availability)

#### 13. Reviews & Ratings System
**Status**: No review system
- [ ] Product reviews and ratings
- [ ] Company/supplier ratings
- [ ] Review moderation system
- [ ] Review endpoints (CRUD)
- [ ] Rating aggregation and display
- [ ] Review helpfulness voting

#### 14. Multi-Language Support (i18n)
**Status**: No internationalization
- [ ] Laravel localization setup
- [ ] Arabic and English language files
- [ ] Language switching endpoint
- [ ] Translated product names and descriptions
- [ ] Localized date/time formats
- [ ] RTL (Right-to-Left) layout support for Arabic
- [ ] Language preference per user

#### 15. Inventory Management Enhancements
**Status**: Basic inventory exists, but limited features
- [ ] Expiry date tracking for products
- [ ] Batch/lot number tracking
- [ ] Stock transfer between suppliers
- [ ] Bulk stock operations (import/export)
- [ ] Stock adjustment reasons and notes
- [ ] Inventory valuation
- [ ] Stock forecasting

### 🔵 Security & Quality Missing Features

These features improve security, performance, and code quality.

#### 16. Security Enhancements
**Status**: Basic security exists, but can be enhanced
- [ ] Two-factor authentication (2FA) implementation
- [ ] Advanced rate limiting (per user/company/IP)
- [ ] IP whitelisting for admin endpoints
- [ ] Comprehensive audit logging system
- [ ] Data encryption at rest
- [ ] API key management for integrations
- [ ] Security headers configuration
- [ ] Regular security audit process
- [ ] Vulnerability scanning integration

#### 17. Performance Optimizations
**Status**: Basic performance, but can be optimized
- [ ] Redis caching implementation (currently file-based)
- [ ] API response caching
- [ ] Database query optimization and indexing review
- [ ] CDN integration for static assets and images
- [ ] Image optimization and lazy loading
- [ ] Database connection pooling optimization
- [ ] API response compression
- [ ] Eager loading optimization audit

#### 18. User Management (Admin)
**Status**: Basic user management, but no admin endpoints
- [ ] Admin endpoints to manage users:
  - [ ] `GET /api/v1/admin/users` - List all users
  - [ ] `POST /api/v1/admin/users` - Create user
  - [ ] `PUT /api/v1/admin/users/{id}` - Update user
  - [ ] `DELETE /api/v1/admin/users/{id}` - Delete/deactivate user
- [ ] User role management
- [ ] User activation/deactivation
- [ ] Bulk user operations
- [ ] User activity tracking
- [ ] User import/export

#### 19. Additional Missing Features
- [ ] Product comparison functionality
- [ ] Subscription management for suppliers
- [ ] Loyalty program integration
- [ ] Referral system
- [ ] Product variants (sizes, colors, etc.)
- [ ] Bulk product import/export
- [ ] Product templates
- [ ] Company verification system
- [ ] Document management (contracts, licenses)
- [ ] API webhooks for external integrations
- [ ] GraphQL API (optional, for advanced queries)

---

## 📊 Statistics

-   **Total Endpoints**: 85+ routes (including cart, offers, drivers, profile, categories, manufacturers, cities, regions, invoices, buyer-supplier relationships, price lists endpoints)
-   **Completed Features**: 110+ (including all new requirements and documentation updates)
-   **Database Seeders**: 10 (CitySeeder, RegionSeeder, CompanySeeder, CategorySeeder, ManufacturerSeeder, UserSeeder, ProductSeeder, OfferSeeder, BuyerSupplierRelationshipSeeder, OrderSeeder)
-   **Pending Tasks**: See "Missing Features & Requirements" section below
-   **Critical Missing Features**: 5 (Invoice API, Product Images, Export API, Notification Infrastructure, Testing Suite)
-   **High Priority Missing Features**: 5 (Driver Availability, Payment Integration, Analytics, Advanced Search, Real-Time)
-   **Medium Priority Missing Features**: 5 (Order Templates, Wishlist, Reviews, Multi-Language, Inventory Enhancements)
-   **Security & Quality Missing Features**: 4 (Security Enhancements, Performance Optimizations, User Management, Additional Features)
-   **Documentation Files**: 10 (README.md, DEPLOYMENT.md, PROGRESS.md, QUEUE_WORKERS.md, 6 Domain READMEs)
-   **Background Jobs**: 4 (SendOrderNotificationJob, GenerateInvoiceJob, LowStockAlertJob, ExportOrdersJob)
-   **Deployment Files**: 3 (Procfile, render.yaml, railway.json)
-   **Observers**: 3 (OrderObserver, StockMovementObserver, ProductObserver)
-   **Console Commands**: 1 (SyncSupplierCategoriesAndManufacturers)
-   **Domain Services**: 2 (CartOfferService - automatic and manual offer detection/application in cart; PricingService - central price resolution engine)
-   **New Models**: 3 (PriceList, BuyerSupplierRelationship, SalesmanClient)
-   **New Constants**: 4 (SupplierClassification, BuyerClassification, PricingType, PaymentTerms)
-   **Invoice Management**: Full API with status tracking, payment management, and file downloads
-   **Location System**: Cities and Regions (hierarchical), Driver-region assignments
-   **Geospatial Features**: Company coordinates (latitude, longitude) for delivery tracking
-   **Middleware**: 1 (CleanupTransactions)
-   **Database Connections**: 2 (pooled for API, unpooled for seeders)
-   **Cache Driver**: File-based (Redis ready)
-   **Swagger Documentation**: Fully updated with all new fields, multi-supplier order handling, categories, manufacturers, cities, regions, driver-region management, date range filtering, invoice management, manual offer selection
-   **Location Management**: Cities (8), Regions (20+), Driver-region assignments, Company coordinates

---

## 🎯 Next Steps

### Immediate Priority (New Requirements) ✅ ALL COMPLETED

1. ✅ ~~**Product Enhancements**~~ - COMPLETED (category, dual pricing, discount)
2. ✅ ~~**Offers/Promotions System**~~ - COMPLETED
3. ✅ ~~**Cart Management**~~ - COMPLETED (with multi-supplier checkout - Option A)
4. ✅ ~~**Multi-Supplier Order Handling**~~ - COMPLETED (Option A: Backend splits orders)
5. ✅ ~~**Driver Management**~~ - COMPLETED
6. ✅ ~~**Profile Management**~~ - COMPLETED

### Previous Tasks (Still Pending)

7. ✅ ~~**Product Enhancements**~~ - COMPLETED (category, dual pricing, discount)
8. ✅ ~~**Offers/Promotions System**~~ - COMPLETED
9. ✅ ~~**Cart Management**~~ - COMPLETED (with multi-supplier checkout)
10. ✅ ~~**Driver Management**~~ - COMPLETED
11. ✅ ~~**Profile Management**~~ - COMPLETED
12. ✅ ~~**Create Domain READMEs**~~ - COMPLETED (All 5 domains documented: Orders, Inventory, Products, Companies, Deliveries)
13. **Create Background Jobs** (Medium Priority)
14. **Enhanced Error Handling** (Medium Priority - further improvements)

### Completed Tasks

-   ✅ ~~Implement Model Observers~~ - COMPLETED
-   ✅ ~~Create Stock Management Endpoints~~ - COMPLETED
-   ✅ ~~Implement Automatic Stock Deduction~~ - COMPLETED
-   ✅ ~~Create Database Seeders~~ - COMPLETED (with Arabic data)
-   ✅ ~~Fix Transaction Errors~~ - COMPLETED (middleware + file cache)
-   ✅ ~~Improve Error Handling~~ - COMPLETED (API exception handling)
-   ✅ ~~Code Cleanup~~ - COMPLETED
-   ✅ ~~Documentation Consolidation~~ - COMPLETED

---

_This file is automatically updated as development progresses._
