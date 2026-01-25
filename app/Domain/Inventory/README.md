# Inventory Domain

This domain handles all inventory and stock management operations in the Med Market B2B system. It ensures accurate stock tracking, maintains audit trails, and prevents stock inconsistencies.

## Overview

The Inventory domain manages product stock quantities and maintains a complete audit trail of all stock movements. It ensures data integrity through transactional operations and prevents negative stock scenarios.

## Key Concepts

### Stock Movements

Every change to product stock is recorded as a movement:
- **Type**: `in` (stock increase) or `out` (stock decrease)
- **Quantity**: Positive for `in`, negative for `out`
- **Context**: Order ID, user ID, reason, and reference information
- **Audit Trail**: Before/after quantities are recorded

### Stock Movement Types

- **in** - Stock increase (purchases, returns, adjustments)
- **out** - Stock decrease (sales, orders, adjustments)

### Stock Safety

- Stock cannot go negative
- Insufficient stock throws exceptions
- All stock changes are transactional

## Domain Services

### StockMovementService

**Purpose**: Records stock movements and updates product stock quantities.

**Key Responsibilities**:
- Record stock movements with full audit trail
- Update product stock quantities atomically
- Prevent negative stock scenarios
- Log all movements to application log
- Support both direct and convenience methods

**Usage Example**:
```php
use App\Domain\Inventory\StockMovementService;
use App\Models\Product;

$stockService = app(StockMovementService::class);

// Record stock increase (incoming stock)
$movement = $stockService->recordIn(
    product: $product,
    quantity: 100,
    userId: $currentUserId,
    reason: 'Purchase from supplier'
);

// Record stock decrease (outgoing stock)
$movement = $stockService->recordOut(
    product: $product,
    quantity: 10,
    orderId: $orderId,
    userId: $currentUserId,
    reason: 'Order ORD-20260125-ABC123'
);

// Record custom movement
$movement = $stockService->recordMovement(
    product: $product,
    type: 'in',
    quantity: 50,
    orderId: null,
    userId: $currentUserId,
    reason: 'Stock adjustment',
    referenceType: 'adjustment',
    referenceId: 'ADJ-001'
);
```

**Method Signatures**:

```php
// Record stock increase (convenience method)
public function recordIn(
    Product $product,
    int $quantity,
    ?int $userId = null,
    ?string $reason = null
): StockMovement

// Record stock decrease (convenience method)
public function recordOut(
    Product $product,
    int $quantity,
    ?int $orderId = null,
    ?int $userId = null,
    ?string $reason = null
): StockMovement

// Record custom movement (full control)
public function recordMovement(
    Product $product,
    string $type,
    int $quantity,
    ?int $orderId = null,
    ?int $userId = null,
    ?string $reason = null,
    ?string $referenceType = null,
    ?string $referenceId = null
): StockMovement
```

**Parameters**:
- `$product` - Product model instance
- `$type` - Movement type: `'in'` or `'out'`
- `$quantity` - Quantity (positive for `in`, negative for `out`)
- `$orderId` - Optional order ID (for order-related movements)
- `$userId` - Optional user ID performing the movement
- `$reason` - Optional reason/description for the movement
- `$referenceType` - Optional reference type (e.g., 'adjustment', 'return')
- `$referenceId` - Optional reference ID

**Returns**: `StockMovement` model instance

**Exceptions**:
- `InvalidArgumentException` - If quantity is zero or stock would go negative

**Stock Calculation**:
- `quantity_after = quantity_before + quantity`
- For `in`: quantity is positive (e.g., +100)
- For `out`: quantity is negative (e.g., -10)
- Negative stock is prevented (throws exception)

## Related Models

- `App\Models\Product` - Product model (stock is updated)
- `App\Models\StockMovement` - Stock movement audit records
- `App\Models\Order` - Orders that trigger stock movements
- `App\Models\User` - Users performing stock operations

## Integration Points

### With Orders Domain

Inventory automatically interacts with orders:
- Stock is deducted when order is accepted/prepared
- Stock is reverted when order is cancelled
- Stock movements are linked to orders via `order_id`

### With Product Management

Product stock is automatically updated:
- Stock quantity is updated atomically
- Stock movements are recorded for audit
- Low stock warnings can be triggered (via observers)

## Business Rules

1. **Stock Movements**:
   - Every stock change must be recorded as a movement
   - Stock cannot go negative
   - All operations are transactional

2. **Audit Trail**:
   - All movements record before/after quantities
   - Movements include user, order, and reason context
   - Movements are logged to application log

3. **Data Integrity**:
   - Stock updates happen in transactions
   - Rollback on any failure
   - Product stock always matches sum of movements

4. **Validation**:
   - Quantity cannot be zero
   - Insufficient stock throws exception
   - Invalid movement types are rejected

## Stock Movement Scenarios

### Order Processing
- **Order Accepted/Prepared**: Stock deducted (`out` movement)
- **Order Cancelled**: Stock reverted (`in` movement)

### Manual Operations
- **Stock Addition**: `recordIn()` for purchases or returns
- **Stock Adjustment**: `recordMovement()` for corrections
- **Stock Deduction**: `recordOut()` for sales or losses

### Reference Tracking
- Movements can reference orders, adjustments, or other entities
- `reference_type` and `reference_id` provide flexible tracking

## Error Handling

- Zero quantity throws `InvalidArgumentException`
- Negative stock throws `InvalidArgumentException` with available quantity
- All operations are transactional (rollback on failure)
- Errors are logged to application log

## Observers

`StockMovementObserver` automatically:
- Logs stock movements to application log
- Triggers low stock warnings
- Logs updates and deletions

## Testing Considerations

- Test stock increase operations
- Test stock decrease operations
- Test negative stock prevention
- Test transaction rollback scenarios
- Test concurrent stock operations
- Test audit trail accuracy
- Test low stock detection

## Future Enhancements

- Stock reservations (hold stock for pending orders)
- Batch stock operations
- Stock transfer between locations
- Stock valuation and costing
- Automated reorder points
- Stock forecasting

