# Companies Domain

This domain handles company-related business logic in the Med Market B2B system. Companies represent suppliers (who provide products) and buyers (pharmacies/salesmen who purchase products).

## Overview

The Companies domain manages company information, relationships, classifications, and business rules. Companies are classified by type (supplier/buyer) and further by sub-classification (supplier: wholesale/retail/hybrid; buyer: pharmacy/salesman).

## Key Concepts

### Company Types

Companies can be one of two types:
- **supplier** - Companies that supply products to buyers
- **buyer** - Companies (pharmacies/salesmen) that purchase products from suppliers

### Supplier Classifications

Suppliers are further classified:
- **wholesale** — Sells products in bulk. Primarily serves salesmen and large buyers. Hidden from pharmacist catalog.
- **retail** — Sells products in standard quantities to pharmacists directly.
- **hybrid** — Operates as both wholesale and retail. Offers different prices depending on buyer type and quantity.

### Buyer Classifications

Buyers are further classified:
- **pharmacy** — End B2B customer. Buys from retail or hybrid suppliers at the pharmacist price.
- **salesman** — Intermediary who buys in bulk at wholesale prices and resells to pharmacists. Can be independent or supplier-associated.

### Company Structure

Companies contain:
- **Basic Information**: Name, type, address, contact details
- **Classifications**: `supplier_classification` (wholesale/retail/hybrid), `buyer_classification` (pharmacy/salesman)
- **Financial**: `min_order_amount` (minimum order value for wholesale)
- **Location Information**: Address, coordinates (latitude, longitude), region_id, address_line
- **Relationships**: Users, products (suppliers), orders (buyers/suppliers), region (with city), price lists, buyer-supplier relationships
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

### Type & Classification Checking

```php
// Check company type
$company->isSupplier();
$company->isBuyer();

// Check supplier classification
$company->isWholesale();     // wholesale or hybrid
$company->isRetail();        // retail or hybrid
$company->isHybrid();        // hybrid only

// Check buyer classification
$company->isPharmacy();
$company->isSalesmanCompany();

// Check pricing eligibility
$company->canOfferWholesaleTo($buyerCompany);
```

### Relationships

- `users()` - Has many users
- `products()` - Has many products (suppliers only)
- `buyerOrders()` - Has many orders as buyer
- `supplierOrders()` - Has many orders as supplier
- `categories()` - Belongs to many categories (suppliers only, auto-synced from products)
- `manufacturers()` - Belongs to many manufacturers (suppliers only, auto-synced from products)
- `region()` - Belongs to region (with city information)
- `supplierPriceLists()` - Has many price lists as supplier
- `buyerPriceLists()` - Has many price lists as buyer
- `buyerRelationships()` - Has many buyer relationships (as supplier)
- `supplierRelationships()` - Has many supplier relationships (as buyer)
- `salesmanAssignments()` - Has many salesman client entries (as pharmacy)

## Buyer-Supplier Relationships

The `buyer_supplier_relationships` table manages the financial relationship between buyers and suppliers:
- **Payment Terms**: cash, net_15, net_30, net_60
- **Credit Limits**: Maximum credit extended to a buyer
- **Current Balance**: Outstanding credit balance
- **Approval Status**: Whether the relationship is approved

```php
// Check credit availability
$relationship->hasCreditAvailable($orderAmount);

// Calculate payment due date
$dueDate = $relationship->calculateDueDate($orderDate);
```

## Salesman Support

Companies support two types of salesmen:

### Supplier-Associated Salesman
- User with `role = salesman` and `company_id` pointing to a supplier company
- Managed by the supplier (similar to drivers)
- Can place orders on behalf of pharmacists
- Assigned to regions via `salesman_regions` table

### Independent Salesman
- User with `role = salesman` and their own buyer company (`buyer_classification = salesman`)
- Operates independently, buys from any wholesale/hybrid supplier
- Resells to pharmacists at their own margin

### Salesman-Pharmacy Clients
The `salesman_clients` table tracks which pharmacies a salesman serves:
- `salesman_user_id` — For supplier-associated salesmen
- `salesman_company_id` — For independent salesmen
- `pharmacy_company_id` — The pharmacy being served

## Future Domain Services

### CompanyManagementService (Planned)
- Bulk company operations
- Company statistics and analytics
- Company verification and approval
- Company subscription management

### BuyerSupplierRelationshipService (Planned)
- Manage buyer-supplier relationships
- Credit limit management and balance tracking
- Payment terms negotiation
- Relationship approval workflows

## Related Models

- `App\Models\Company` - Main company model
- `App\Models\User` - Users belonging to companies
- `App\Models\Product` - Products from supplier companies
- `App\Models\Category` - Categories (for supplier relationships)
- `App\Models\Manufacturer` - Manufacturers (for supplier relationships)
- `App\Models\Order` - Orders linking buyer and supplier companies
- `App\Models\Cart` - Carts belonging to buyer companies
- `App\Models\PriceList` - Custom per-buyer pricing
- `App\Models\BuyerSupplierRelationship` - Financial relationship between buyer and supplier
- `App\Models\SalesmanClient` - Salesman-pharmacy client assignments

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
   - Suppliers must have `supplier_classification` (wholesale/retail/hybrid)
   - Buyers must have `buyer_classification` (pharmacy/salesman)
   - Name must be unique

2. **Company Types & Classifications**:
   - Suppliers can create products
   - Buyers can create orders
   - Wholesale-only suppliers are hidden from pharmacist catalogs
   - Hybrid suppliers offer both wholesale and retail pricing
   - Salesman buyers can access wholesale pricing when qty thresholds are met
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
- `App\Constants\SupplierClassification` - Supplier sub-types (`WHOLESALE`, `RETAIL`, `HYBRID`)
- `App\Constants\BuyerClassification` - Buyer sub-types (`PHARMACY`, `SALESMAN`)
- `App\Constants\PaymentTerms` - Payment term options (`CASH`, `NET_15`, `NET_30`, `NET_60`)

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

## Buyer-Supplier Relationships API (Phase 4 — Implemented)

Full CRUD + credit management via `BuyerSupplierRelationshipController`:
- Create/update/delete relationships between buyer and supplier companies
- Approve/reject workflow
- Credit limit management with balance tracking
- Credit availability checking
- Audit-logged balance adjustments

## Custom Price Lists API (Phase 4 — Implemented)

Suppliers can set negotiated per-buyer pricing via `PriceListController`:
- CRUD for custom price entries (buyer + product + optional min_qty)
- Date range validation (valid_from / valid_to)
- Batch view of all currently valid prices for a buyer
- Integrated with `PricingService` — custom prices have highest resolution priority

## Future Enhancements

- Company verification and approval workflow
- Company profiles and branding
- Company ratings and reviews
- Company subscription plans (Basic/Professional/Enterprise)
- Company analytics dashboard
- Company communication features
- Company document management
- Salesman performance metrics and commission tracking
- Salesman territory management (exclusive regions)
- Credit limit auto-adjustment based on payment history
- Supplier category/manufacturer analytics

