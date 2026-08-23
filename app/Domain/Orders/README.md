# Orders Domain

This domain handles all order-related business logic in the Med Market B2B system. Orders represent transactions between buyer companies (pharmacies) and supplier companies.

## Overview

The Orders domain manages the complete order lifecycle from creation to delivery or cancellation. It ensures data integrity through transactional operations, validates state transitions, and automatically handles inventory operations.

## Key Concepts

### Order States

Orders progress through a defined state machine:

1. **pending** - Initial state when order is created
2. **accepted** - Supplier has accepted the order
3. **prepared** - Order items have been prepared/packed
4. **shipped** - Order has been shipped to buyer
5. **delivered** - Order has been delivered to buyer (final state)
6. **cancelled** - Order has been cancelled (final state)

### State Transitions

Valid transitions are enforced:

-   `pending` → `accepted` or `cancelled`
-   `accepted` → `prepared` or `cancelled`
-   `prepared` → `shipped` or `cancelled`
-   `shipped` → `delivered`
-   `delivered` → (no transitions - final state)
-   `cancelled` → (no transitions - final state)

### Multi-Supplier Orders

When a cart contains items from multiple suppliers, the system automatically splits the checkout into multiple orders (one per supplier). Each order is independent and can be managed separately.

## Domain Services

### OrderCreationService

**Purpose**: Creates new orders with items and calculates totals.

**Key Responsibilities**:

-   Generate unique order numbers (format: `ORD-YYYYMMDD-RANDOM6`)
-   Calculate subtotal, tax, and total amounts
-   Create order and order items in a single transaction
-   Handle transaction nesting (for use in seeders or other transactions)
-   Retry on unique constraint violations

**Usage Example**:

```php
use App\Domain\Orders\OrderCreationService;

$orderCreationService = app(OrderCreationService::class);

$order = $orderCreationService->create(
    buyerCompanyId: $buyerCompanyId,
    supplierCompanyId: $supplierCompanyId,
    createdBy: $userId,
    items: [
        [
            'product_id' => 1,
            'quantity' => 10,
            'unit_price' => 15.50,
            'notes' => 'Optional item notes'
        ],
        // ... more items
    ],
    notes: 'Order notes',
    taxRate: 15.0 // 15% tax
);
```

**Method Signature**:

```php
public function create(
    int $buyerCompanyId,
    int $supplierCompanyId,
    int $createdBy,
    array $items,
    ?string $notes = null,
    float $taxRate = 0.0,
    ?string $paymentMethod = null,
    ?int $placedBySalesmanId = null
): Order
```

**Parameters**:

-   `$buyerCompanyId` - ID of the buyer company (pharmacy)
-   `$supplierCompanyId` - ID of the supplier company
-   `$createdBy` - ID of the user creating the order
-   `$items` - Array of items with `product_id`, `quantity`, `unit_price`, `pricing_type`, and optional `notes`
-   `$notes` - Optional order-level notes
-   `$taxRate` - Tax rate as percentage (e.g., 15.0 for 15%)
-   `$paymentMethod` - Optional payment terms (`cash`/`net_15`/`net_30`/`net_60`); non-cash reserves trade credit
-   `$placedBySalesmanId` - Optional salesman attribution, set atomically with order creation

> **Pricing integrity**: unit prices passed into this service must come from
> `PricingService::resolve` — HTTP endpoints never accept client-supplied prices.
> Negotiated pricing belongs in custom price lists, which the resolver already honours.

**Returns**: `Order` model instance with items loaded

**Exceptions**:

-   `InvalidArgumentException` - If items array is empty
-   `RuntimeException` - If unique order number generation fails after retries

### OrderStateTransitionService

**Purpose**: Handles order state transitions with validation, logging, and automatic stock operations.

**Key Responsibilities**:

-   Validate state transitions (only allow valid transitions)
-   Automatically deduct stock when order moves to `accepted` or `prepared`
-   Automatically revert stock when order is cancelled
-   Log all state changes to `OrderStatusLog` table
-   Update status-specific timestamps (`accepted_at`, `prepared_at`, etc.)
-   Ensure all operations happen in a single transaction

**Usage Example**:

```php
use App\Domain\Orders\OrderStateTransitionService;

$transitionService = app(OrderStateTransitionService::class);

// Transition order to accepted (will deduct stock)
$order = $transitionService->transition(
    order: $order,
    newStatus: OrderStatus::ACCEPTED,
    userId: $currentUserId,
    notes: 'Order accepted by supplier'
);

// Cancel order (will revert stock if already deducted)
$order = $transitionService->transition(
    order: $order,
    newStatus: OrderStatus::CANCELLED,
    userId: $currentUserId,
    notes: 'Order cancelled by buyer'
);
```

**Method Signature**:

```php
public function transition(
    Order $order,
    string $newStatus,
    ?int $userId = null,
    ?string $notes = null
): Order
```

**Parameters**:

-   `$order` - The order to transition
-   `$newStatus` - Target status (must be valid transition)
-   `$userId` - Optional user ID performing the transition
-   `$notes` - Optional notes for the status change

**Returns**: Updated `Order` model instance

**Exceptions**:

-   `InvalidArgumentException` - If status is invalid or transition is not allowed

**Stock Operations**:

-   **Stock Deduction**: When order transitions to `accepted` or `prepared` (only if coming from `pending` or `accepted`)
-   **Stock Reversion**: When order is cancelled (only if stock was previously deducted)

### InvoicePaymentService

**Purpose**: Marks invoices as paid and releases the matching reserved trade credit as one atomic unit.

**Key Responsibilities**:

-   Lock the invoice row inside a transaction (no concurrent double-payment)
-   Accumulate partial payments (`paid_amount` adds up; capped at the invoice total)
-   Flip `payment_status` to `paid` only when fully settled; partial payments keep the existing invoice status
-   Release the newly-paid portion of reserved trade credit for non-cash orders in the same transaction
-   Idempotent: paying again after full settlement is a no-op (credit is never released twice)

**Usage Example**:

```php
use App\Domain\Orders\InvoicePaymentService;

$service = app(InvoicePaymentService::class);

// Pay a specific amount (or null for the remaining total)
$invoice = $service->markAsPaid($invoice, 200.0);
```

**Exceptions**:

-   `App\Exceptions\DomainException` - If the payment exceeds the invoice total (rendered as 422)

### OrderExportService (Implemented)

Queues async order exports (CSV/JSON) tracked by the `order_exports` table:

-   `request(user, format, filters, ?companyId)` — creates an `OrderExport` record
    (status `queued`) inside a transaction and dispatches `ExportOrdersJob` on the
    `exports` queue.
-   Every export is scoped to **exactly one company** (buyer or supplier side of the
    matching orders), so exports can never cross the tenancy boundary. Users export
    their own company's orders; admins may target any company via `company_id`.
-   The job transitions the record through `queued → processing → completed/failed`
    (`App\Constants\ExportStatus`), stores the generated file path and order count,
    and marks failures with a generic error message.
-   API: `POST /orders/exports` (202), `GET /orders/exports`,
    `GET /orders/exports/{id}` (poll status), `GET /orders/exports/{id}/download`.

### Order state machine — `App\Constants\OrderStatus`

**Purpose**: Single source of truth for order states, valid transitions, and validation.
(The former `App\Domain\Orders\OrderState` duplicate was removed — it diverged from
`OrderStatus`. Use `OrderStatus` everywhere.)

**Key Methods**:

-   `all()` - Get all valid order states
-   `isValid(string $status)` - Check if a status is valid
-   `getValidTransitions(string $fromStatus)` - Get valid next states for a given status
-   `canTransition(string $from, string $to)` - Check if a transition is valid

**Transitions**: `pending → accepted|cancelled`, `accepted → prepared|cancelled`,
`prepared → shipped|cancelled`, `shipped → delivered` (a shipped order **cannot** be
cancelled), `delivered`/`cancelled` are final.

**Usage Example**:

```php
use App\Constants\OrderStatus;

// Check if status is valid
if (OrderStatus::isValid($status)) {
    // Process status
}

// Get valid next states
$nextStates = OrderStatus::getValidTransitions(OrderStatus::PENDING);
// Returns: ['accepted', 'cancelled']

// Check if transition is valid
if (OrderStatus::canTransition(OrderStatus::PENDING, OrderStatus::ACCEPTED)) {
    // Transition is valid
}
```

## Related Models

-   `App\Models\Order` - Main order model
-   `App\Models\OrderItem` - Order line items
-   `App\Models\OrderStatusLog` - Audit trail of status changes
-   `App\Models\Invoice` - Invoice records linked to orders
-   `App\Models\Company` - Buyer and supplier companies (includes region information)
-   `App\Models\User` - Order creator and assigned driver
-   `App\Models\Product` - Products in order items (includes category and manufacturer)
-   `App\Models\Region` - Region information for companies (includes city)

## Integration Points

### With Inventory Domain

Orders automatically interact with inventory:

-   Stock is deducted when order is accepted/prepared
-   Stock is reverted when order is cancelled
-   Uses `StockMovementService` for all stock operations

### With Cart Management

Cart checkout uses `OrderCreationService` to create orders:

-   Cart items are grouped by supplier
-   Multiple orders are created (one per supplier)
-   All orders created in single atomic transaction

### With Deliveries Domain

Orders include location information for driver assignment:

-   Orders include buyerCompany with region information (region and city)
-   Driver assignment considers pharmacy region
-   System warns (but doesn't block) if driver doesn't serve pharmacy's region
-   Suppliers can see pharmacy location when assigning drivers

### With Invoice Management

Orders automatically generate invoices when accepted:

-   Invoices are automatically generated when an order transitions to `accepted` status
-   Invoice generation is handled by `GenerateInvoiceJob` (dispatched automatically)
-   Each order has a `HasOne` relationship to an `Invoice` model
-   Invoice records are stored in the `invoices` table with status tracking
-   Invoice files are stored in `storage/app/invoices/` directory
-   Invoice statuses: `generated`, `sent`, `paid`, `overdue`, `cancelled`
-   Payment statuses: `pending`, `paid`, `partial`, `overdue`, `refunded`
-   API endpoints available for invoice management (`/api/v1/invoices`, `/api/v1/orders/{id}/invoice`)

## Business Rules

1. **Order Creation**:

    - Order must have at least one item
    - Order number must be unique (auto-generated)
    - Totals are calculated automatically

2. **State Transitions**:

    - Only valid transitions are allowed
    - Invalid transitions throw `InvalidArgumentException`
    - State changes are logged to `OrderStatusLog`

3. **Stock Operations**:

    - Stock is deducted when order is accepted/prepared
    - Stock is reverted if order is cancelled
    - Insufficient stock prevents order acceptance
    - Stock operations are transactional (rollback on failure)

4. **Multi-Supplier Handling**:

    - Cart checkout creates multiple orders (one per supplier)
    - Each order is independent
    - All orders created atomically (all succeed or all fail)

5. **Audit Trail**:
    - All status changes are logged
    - Logs include user, timestamps, and notes
    - Status-specific timestamps are updated automatically

6. **Order Filtering**:
    - Orders can be filtered by status, supplier_company_id, buyer_company_id
    - Date range filtering: `from_date` and `to_date` (YYYY-MM-DD format)
    - Filter by created_at timestamp
    - Useful for reporting and analytics

## Error Handling

-   Invalid state transitions throw `InvalidArgumentException`
-   Stock operations throw `InvalidArgumentException` if insufficient stock
-   All operations are transactional (rollback on any failure)
-   Unique constraint violations are handled with retry logic

## Testing Considerations

-   Test valid and invalid state transitions
-   Test stock deduction and reversion
-   Test multi-supplier order creation
-   Test transaction rollback scenarios
-   Test unique order number generation
-   Test concurrent order creation

## API Endpoints

### List Orders (`GET /api/v1/orders`)

**Filtering Options:**
- `?status=pending` - Filter by order status
- `?supplier_company_id=1` - Filter by supplier (for buyers)
- `?buyer_company_id=1` - Filter by buyer (for suppliers)
- `?from_date=2026-01-01` - Filter orders created from date (YYYY-MM-DD)
- `?to_date=2026-01-31` - Filter orders created up to date (YYYY-MM-DD)

**Response Includes:**
- Order details with buyerCompany (includes region and city)
- Supplier company with categories and manufacturers
- Order items with product details (category, manufacturer)
- Driver information (if assigned)

### Assign Driver (`POST /api/v1/orders/{id}/assign-driver`)

- Suppliers can assign drivers to their orders
- System checks if driver serves pharmacy's region (warning logged if not)
- Driver must belong to supplier's company

## Payment & Credit

Orders now support payment terms and credit:
- `payment_method` — cash or credit (default: cash)
- `payment_due_date` — calculated from buyer-supplier relationship payment terms
- Payment terms are managed per buyer-supplier relationship (cash, net_15, net_30, net_60)
- Credit limits are enforced via `BuyerSupplierRelationship`

## Salesman Orders

Orders can be placed by salesmen on behalf of pharmacists:
- `placed_by_salesman_id` — references the salesman user who placed the order
- The `buyer_company_id` is the pharmacy's company
- The salesman can be supplier-associated or independent
- `Order::isPlacedBySalesman()` checks if a salesman placed the order
- `Order::salesman()` relationship returns the salesman user

### On-Behalf Ordering API (Phase 5)
- `POST /salesmen/{id}/place-order` — full on-behalf ordering endpoint
- Validates the pharmacy is an active client of the salesman
- Pricing is resolved using `PricingService` from the **buyer company's perspective** (not the salesman's)
- Payment terms: if `payment_method` is not `cash`, auto-sets `payment_due_date` from the buyer-supplier relationship
- The order's `created_by` is set to the salesman user ID for audit trail

## Pricing Type Tracking

Each `OrderItem` now records the pricing tier that was applied:
- `pricing_type` — `wholesale`, `retail`, or `custom`
- This provides an audit trail of which price was used for each line item
- `OrderController::store()` uses `PricingService::resolve()` to automatically determine the correct price when `unit_price` is not provided
- `OrderCreationService::create()` accepts and persists `pricing_type` alongside each order item

## Future Enhancements

-   Order cancellation reasons
-   Partial order fulfillment
-   Order modification (before acceptance)
-   Order history and versioning
-   Order templates/favorites
-   Bulk order operations
-   Automatic driver assignment based on region
-   Order analytics by region
-   Delivery time estimation based on distance
-   Salesman commission tracking per order
-   Credit balance auto-update on order completion/payment
