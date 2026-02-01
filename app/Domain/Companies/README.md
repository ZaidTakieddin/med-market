# Companies Domain

This domain handles company-related business logic in the Med Market B2B system. Companies represent suppliers (who provide products) and buyers (pharmacies who purchase products).

## Overview

The Companies domain manages company information, relationships, and business rules. While company CRUD operations are handled in controllers, this domain is prepared for future company-specific business logic and services.

## Key Concepts

### Company Types

Companies can be one of two types:
- **supplier** - Companies that supply products to buyers
- **buyer** - Companies (pharmacies) that purchase products from suppliers

### Company Structure

Companies contain:
- **Basic Information**: Name, type, address, contact details
- **Location Information**: Address, coordinates (latitude, longitude), region_id, address_line
- **Relationships**: Users, products (suppliers), orders (buyers/suppliers), region (with city)
- **Supplier Metadata**: Categories and manufacturers (auto-synced from products)
- **Metadata**: Additional company-specific data

### Multi-Tenancy

The system uses company-based multi-tenancy:
- Users belong to companies
- Products belong to supplier companies
- Orders link buyer and supplier companies
- Authorization is company-scoped

## Current Implementation

### Controllers

Company operations are handled in `CompanyController`:
- List companies with filtering
  - Filter by `category_id` (for suppliers)
  - Filter by `manufacturer_id` (for suppliers)
  - Search by company name, email, category names, or manufacturer names
- Create companies (admin only)
- Get company details (includes categories and manufacturers for suppliers)
- Update companies
- Delete companies (soft delete)

### Validation

Company validation is handled in:
- `CreateCompanyRequest` - For creating companies
- `UpdateCompanyRequest` - For updating companies

### Business Logic

Currently, company business logic is in:
- **Models**: Relationships, type checking
- **Controllers**: Filtering, authorization
- **Policies**: Authorization rules (`CompanyPolicy`)

## Company Model Features

### Type Checking

```php
// Check if company is a supplier
if ($company->isSupplier()) {
    // Handle supplier logic
}

// Check if company is a buyer
if ($company->isBuyer()) {
    // Handle buyer logic
}
```

### Relationships

- `users()` - Has many users
- `products()` - Has many products (suppliers only)
- `buyerOrders()` - Has many orders as buyer
- `supplierOrders()` - Has many orders as supplier
- `categories()` - Belongs to many categories (suppliers only, auto-synced from products)
- `manufacturers()` - Belongs to many manufacturers (suppliers only, auto-synced from products)
- `region()` - Belongs to region (with city information)

## Future Domain Services

This domain is prepared for future services:

### CompanyManagementService (Planned)

**Purpose**: Handle complex company operations, bulk updates, company analytics.

**Potential Features**:
- Bulk company operations
- Company statistics and analytics
- Company verification and approval
- Company subscription management

### CompanyRelationshipService (Planned)

**Purpose**: Manage relationships between companies.

**Potential Features**:
- Supplier-buyer relationships
- Preferred supplier lists
- Company partnerships
- Relationship history

### CompanySettingsService (Planned)

**Purpose**: Manage company-specific settings and preferences.

**Potential Features**:
- Company settings management
- Notification preferences
- Payment terms
- Delivery preferences

## Related Models

- `App\Models\Company` - Main company model
- `App\Models\User` - Users belonging to companies
- `App\Models\Product` - Products from supplier companies
- `App\Models\Category` - Categories (for supplier relationships)
- `App\Models\Manufacturer` - Manufacturers (for supplier relationships)
- `App\Models\Order` - Orders linking buyer and supplier companies
- `App\Models\Cart` - Carts belonging to buyer companies

## Integration Points

### With Users Domain

Companies have users:
- Users belong to companies
- Company type determines user roles
- Company-scoped authorization

### With Products Domain

Supplier companies have products:
- Products belong to supplier companies
- Company filtering for products
- Supplier-specific product management
- **Auto-Sync**: Supplier categories and manufacturers are automatically synced from their active products
  - When a supplier creates/updates/deletes products, their categories/manufacturers are automatically updated
  - Suppliers cannot manually add/remove categories/manufacturers (ensures data reliability)
  - Only active products with category_id/manufacturer_id are considered for syncing

### With Orders Domain

Companies participate in orders:
- Orders link buyer and supplier companies
- Company filtering for orders
- Company-specific order statistics

### With Cart Management

Buyer companies have carts:
- Carts belong to buyer companies
- Company-scoped cart access
- Multi-supplier cart checkout

## Business Rules

1. **Company Creation**:
   - Admin-only operation
   - Type must be `supplier` or `buyer`
   - Name must be unique

2. **Company Types**:
   - Suppliers can create products
   - Buyers can create orders
   - Companies can have multiple users

3. **Authorization**:
   - Users can only access their own company data
   - Suppliers can only manage their own products
   - Buyers can only manage their own orders
   - Admins have full access

4. **Soft Deletes**:
   - Companies use soft deletes
   - Deleted companies are hidden from listings
   - Relationships are preserved

5. **Multi-Tenancy**:
   - All operations are company-scoped
   - Users cannot access other companies' data
   - Policies enforce company boundaries

## Constants

- `App\Constants\CompanyType` - Company type constants (`SUPPLIER`, `BUYER`)

## Authorization

`CompanyPolicy` enforces:
- View any company (authenticated users)
- Create companies (admin only)
- Update companies (admin or company admin)
- Delete companies (admin only)

## Testing Considerations

- Test company creation with different types
- Test company filtering
- Test authorization rules
- Test company relationships
- Test soft delete behavior
- Test multi-tenancy isolation
- Test company-scoped queries
- Test supplier categories/manufacturers auto-sync
- Test filtering suppliers by category_id/manufacturer_id
- Test searching suppliers by category/manufacturer names

## Auto-Sync System

Supplier categories and manufacturers are automatically synced from their products:

- **ProductObserver** monitors product changes and syncs supplier relationships
- Only active products with `category_id` and `manufacturer_id` are considered
- Suppliers cannot manually control their categories/manufacturers (ensures data reliability)
- Sync happens automatically when:
  - Products are created
  - Products are updated (category_id, manufacturer_id, or supplier_company_id changes)
  - Products are deleted or restored
- Manual sync available via command: `php artisan suppliers:sync-categories-manufacturers`

## Future Enhancements

- Company verification and approval workflow
- Company profiles and branding
- Company ratings and reviews
- Company subscription plans
- Company analytics dashboard
- Company communication features
- Company document management
- Company payment terms management
- Supplier category/manufacturer analytics

