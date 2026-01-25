# Med Market - Development Progress Tracker

> Last Updated: 2026-01-25 (Domain READMEs created for all domains: Orders, Inventory, Products, Companies, Deliveries)

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
-   [x] Companies table (suppliers & buyers)
-   [x] Products table with stock tracking
-   [x] Orders table with status management
-   [x] Order items table
-   [x] Stock movements table (audit trail)
-   [x] Order status logs table (audit trail)
-   [x] Personal access tokens table (Sanctum)
-   [x] Cache, jobs, sessions tables

### Models & Relationships

-   [x] User model with Sanctum integration
-   [x] Company model with relationships
-   [x] Product model with stock tracking
-   [x] Order model with state management
-   [x] OrderItem model
-   [x] StockMovement model
-   [x] OrderStatusLog model
-   [x] All model relationships defined

### Constants & Enums

-   [x] OrderStatus constants with transition validation
-   [x] UserRole constants
-   [x] CompanyType constants
-   [x] StockMovementType constants

### Domain Services

-   [x] OrderCreationService - Creates orders with items and calculates totals
-   [x] OrderStateTransitionService - Handles order state transitions with validation and logging
-   [x] StockMovementService - Records stock movements and updates inventory

### Authentication System

-   [x] Register endpoint (`POST /api/v1/auth/register`)
-   [x] Login endpoint (`POST /api/v1/auth/login`) with rate limiting
-   [x] Logout endpoint (`POST /api/v1/auth/logout`)
-   [x] Get authenticated user (`GET /api/v1/auth/me`)
-   [x] Form request validation (RegisterRequest, LoginRequest)

### Order Management API

-   [x] List orders (`GET /api/v1/orders`) - Filtered by company and status
-   [x] Create order (`POST /api/v1/orders`) - Buyers only
-   [x] Get order details (`GET /api/v1/orders/{id}`)
-   [x] Update order status (`POST /api/v1/orders/{id}/status`)
-   [x] Cancel order (`POST /api/v1/orders/{id}/cancel`)
-   [x] Form request validation (CreateOrderRequest, UpdateOrderStatusRequest)

### Product Management API

-   [x] List products (`GET /api/v1/products`) - With search and filtering
-   [x] Create product (`POST /api/v1/products`) - Suppliers only
-   [x] Get product details (`GET /api/v1/products/{id}`)
-   [x] Update product (`PUT /api/v1/products/{id}`)
-   [x] Delete product (`DELETE /api/v1/products/{id}`)
-   [x] Form request validation (CreateProductRequest, UpdateProductRequest)

### Company Management API

-   [x] List companies (`GET /api/v1/companies`) - With filtering
-   [x] Create company (`POST /api/v1/companies`) - Admin only
-   [x] Get company details (`GET /api/v1/companies/{id}`)
-   [x] Update company (`PUT /api/v1/companies/{id}`)
-   [x] Delete company (`DELETE /api/v1/companies/{id}`)
-   [x] Form request validation (CreateCompanyRequest, UpdateCompanyRequest)

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
-   [x] Product endpoints updated with new fields (category, pharmacist_price, customer_price, discount, discount_type)
    -   [x] Create product endpoint includes all new fields with descriptions
    -   [x] Update product endpoint includes all new fields
    -   [x] List products endpoint documents category filtering
    -   [x] Get product endpoint shows all new fields in response schema
-   [x] Order endpoints updated with multi-supplier order handling documentation
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
-   [x] Observers registered in AppServiceProvider
-   [x] All state changes logged to OrderStatusLog table
-   [x] All stock movements logged with context

### Database Seeders

-   [x] CompanySeeder - Seeds suppliers and buyers with Arabic names and addresses
-   [x] UserSeeder - Seeds users with different roles (admin, supplier, buyer, driver) with Arabic names
    -   [x] Idempotent seeding (updateOrCreate based on email)
    -   [x] Multiple drivers per supplier (if multiple suppliers exist)
-   [x] ProductSeeder - Seeds products with stock, Arabic names and descriptions
    -   [x] Updated with category field
    -   [x] Updated with pharmacist_price and customer_price (dual pricing)
    -   [x] Updated with discount and discount_type fields
    -   [x] Idempotent seeding (updateOrCreate based on SKU)
-   [x] OfferSeeder - Seeds offers/promotions with Arabic names and descriptions
    -   [x] Buy X Get Y offers
    -   [x] Percentage discount offers
    -   [x] Fixed discount offers
    -   [x] Bundle offers
    -   [x] Associates offers with products
-   [x] OrderSeeder - Seeds sample orders with Arabic notes
-   [x] DatabaseSeeder - Orchestrates all seeders in correct order
-   [x] Separate database configuration for seeders (unpooled connection)
-   [x] All seeders use Arabic data for names, addresses, and descriptions
-   [x] All seeders are idempotent (can be run multiple times safely)

### Infrastructure & Reliability

-   [x] Transaction cleanup middleware (CleanupTransactions) - Handles failed transactions
-   [x] API exception handling - Proper JSON error responses for unauthenticated requests
-   [x] File-based cache configuration - Eliminates transaction conflicts
-   [x] Code cleanup - Removed commented code, fixed imports, standardized constants

### Documentation

-   [x] README.md - Consolidated project documentation (includes API docs, seeding, troubleshooting)
-   [x] DEPLOYMENT.md - Production deployment guide
-   [x] PROGRESS.md - Development progress tracker
-   [x] Domain READMEs - Complete documentation for all domains
    -   [x] `app/Domain/Orders/README.md` - Order domain documentation
    -   [x] `app/Domain/Inventory/README.md` - Inventory domain documentation
    -   [x] `app/Domain/Products/README.md` - Product domain documentation
    -   [x] `app/Domain/Companies/README.md` - Company domain documentation
    -   [x] `app/Domain/Deliveries/README.md` - Deliveries domain documentation

---

## 🔄 In Progress

_Currently no tasks in progress_

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
    -   [x] Creates various offer types (Buy X Get Y, percentage discount, fixed discount, bundle)
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

#### 5. Background Jobs

-   [ ] SendOrderNotificationJob - Email notifications on status changes
-   [ ] GenerateInvoiceJob - Invoice generation
-   [ ] LowStockAlertJob - Alert when stock is low
-   [ ] ExportOrdersJob - Heavy exports
-   [ ] Configure queue connection
-   [ ] Set up queue workers

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

#### 7. Error Handling Improvements

-   [x] API exception handling - JSON responses for unauthenticated requests
-   [x] Route not found handling - Proper 401/404 JSON responses
-   [ ] Custom exception handler (enhanced)
-   [ ] Better validation error messages
-   [ ] API error response standardization (enhanced)

### Low Priority

#### 8. Caching (Performance)

-   [ ] Cache product lists
-   [ ] Cache company lists
-   [ ] Cache frequently accessed data
-   [ ] Implement cache invalidation strategies

#### 9. API Resources/Transformers

-   [ ] OrderResource - Transform order data
-   [ ] ProductResource - Transform product data
-   [ ] CompanyResource - Transform company data
-   [ ] Consistent API response structure

#### 10. Testing

-   [ ] Feature tests for authentication endpoints
-   [ ] Feature tests for order endpoints
-   [ ] Feature tests for product endpoints
-   [ ] Feature tests for company endpoints
-   [ ] Unit tests for domain services
-   [ ] Policy tests
-   [ ] Set up PHPUnit configuration

#### 11. Additional Features

-   [ ] User management endpoints (admin)
    -   [ ] List users
    -   [ ] Create user
    -   [ ] Update user
    -   [ ] Delete user
-   [ ] Enhanced filtering/search
    -   [ ] Advanced order filtering
    -   [ ] Advanced product search
-   [ ] Dashboard/Statistics endpoints
    -   [ ] Order statistics
    -   [ ] Stock statistics
    -   [ ] Sales reports

---

## 🔮 Future Requests

### Product Enhancements ✅ COMPLETED

1. **Product Category System** ✅

    - [x] Add `category` field to products table
    - [x] Update Product model and migrations
    - [x] Add category filtering to product list endpoint
    - [x] Update Swagger documentation

2. **Dual Pricing System** ✅

    - [x] Add `pharmacist_price` field (selling price for pharmacist/buyer)
    - [x] Add `customer_price` field (selling price for end customer)
    - [x] Update existing `price` field handling (migration strategy)
    - [x] Update Product model and validation
    - [x] Update product endpoints to handle both prices
    - [x] Update Swagger documentation

3. **Discount System** ✅
    - [x] Add `discount` field (discount amount/percentage)
    - [x] Add `discount_type` field (enum: 'percentage', 'fixed')
    - [x] Update Product model and validation
    - [x] Calculate effective price based on discount (getFinalPharmacistPrice, getFinalCustomerPrice methods)
    - [x] Update product endpoints
    - [x] Update Swagger documentation

### Offers/Promotions System ✅ COMPLETED

4. **Product Offers Management** ✅
    - [x] Create `offers` table with all required fields
    - [x] Create `offer_products` pivot table
    - [x] Create Offer model with relationships
    - [x] Create OfferController with CRUD endpoints
    - [x] Support offer types:
        - Buy X Get Y Free
        - Percentage discount
        - Fixed amount discount
        - Bundle offers
    - [x] Offer validation (dates, active status)
    - [x] Authorization (suppliers can only manage their offers)
    - [x] Swagger documentation

### Cart Management ✅ COMPLETED

5. **Shopping Cart System** ✅

    - [x] Create `carts` table with totals (subtotal, tax_amount, total_amount)
    - [x] Create `cart_items` table
    - [x] Create Cart and CartItem models with relationships
    - [x] Cart management endpoints:
        - [x] `GET /api/v1/cart` - Get current user's cart
        - [x] `POST /api/v1/cart/items` - Add item to cart
        - [x] `PUT /api/v1/cart/items/{id}` - Update cart item quantity
        - [x] `DELETE /api/v1/cart/items/{id}` - Remove item from cart
        - [x] `DELETE /api/v1/cart` - Clear entire cart
        - [x] `POST /api/v1/cart/checkout` - Convert cart to order(s)
    - [x] Cart calculations (subtotal, tax, total)
    - [x] Apply product discounts to cart (uses getFinalPharmacistPrice)
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

### Technical Debt

-   None currently

### Recent Improvements

-   **Transaction Handling**: Implemented global transaction cleanup middleware to handle PostgreSQL connection pooling issues
-   **Error Handling**: Improved API error responses for unauthenticated requests
-   **Cache Strategy**: Configured file-based cache (eliminates transaction conflicts), Redis ready for production
-   **Code Quality**: Cleaned up commented code, standardized imports, fixed constant usage
-   **Documentation**: Consolidated documentation files for better maintainability
-   **Domain Documentation**: Created comprehensive READMEs for all 5 domains (Orders, Inventory, Products, Companies, Deliveries)
-   **Swagger Documentation**: Updated all product endpoints to reflect new fields (category, dual pricing, discounts)
-   **Swagger Documentation**: Updated all order endpoints to document multi-supplier order handling
-   **Seeders**: Updated ProductSeeder with all new fields, created OfferSeeder, made all seeders idempotent
-   **Seeders**: Enhanced UserSeeder with multiple drivers per supplier

---

## 📊 Statistics

-   **Total Endpoints**: 40+ routes (including cart, offers, drivers, profile endpoints)
-   **Completed Features**: 85+ (including all new requirements and documentation updates)
-   **Database Seeders**: 5 (CompanySeeder, UserSeeder, ProductSeeder, OfferSeeder, OrderSeeder)
-   **Pending Tasks**: 7 categories (4 high-priority items completed)
-   **Documentation Files**: 8 (README.md, DEPLOYMENT.md, PROGRESS.md, 5 Domain READMEs)
-   **Deployment Files**: 3 (Procfile, render.yaml, railway.json)
-   **Observers**: 2 (OrderObserver, StockMovementObserver)
-   **Middleware**: 1 (CleanupTransactions)
-   **Database Connections**: 2 (pooled for API, unpooled for seeders)
-   **Cache Driver**: File-based (Redis ready)
-   **Swagger Documentation**: Fully updated with all new fields and multi-supplier order handling

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
