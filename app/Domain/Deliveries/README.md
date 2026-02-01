# Deliveries Domain

This domain handles delivery and driver-related business logic in the Med Market B2B system. This is a new domain prepared for future delivery management features.

## Overview

The Deliveries domain is designed to manage the delivery lifecycle of orders, including driver assignment, delivery tracking, and delivery status management. While basic driver assignment is currently handled in controllers, this domain will centralize all delivery-related business logic.

## Key Concepts

### Delivery Structure

Deliveries will manage:
- **Driver Assignment**: Assigning drivers to orders
- **Delivery Tracking**: Tracking delivery status and location
- **Delivery Routes**: Optimizing delivery routes
- **Delivery History**: Complete audit trail of deliveries

### Driver Management

Drivers are users with the `driver` role:
- Drivers belong to supplier companies
- Drivers can be assigned to orders
- Drivers can view their assigned orders
- Drivers can update delivery status

## Current Implementation

### Driver Assignment

Currently handled in `OrderController`:
- `assignDriver()` - Assigns a driver to an order
- Driver must belong to supplier company
- Authorization checks in place

### Driver Management

Currently handled in `DriverController`:
- List drivers (supplier-scoped)
- Create drivers (supplier/admin only)
- Get driver details
- Update drivers
- Delete drivers
- Get driver's orders
- Get driver's assigned regions (`GET /drivers/{id}/regions`)
- Assign regions to driver (`POST /drivers/{id}/regions`)
- Remove region from driver (`DELETE /drivers/{id}/regions/{region_id}`)

### Models

- `App\Models\User` - Drivers are users with `driver` role
  - `driverRegions()` - Belongs to many regions (via driver_regions pivot)
  - `servesRegion(int $regionId)` - Check if driver serves a specific region
- `App\Models\Order` - Orders have `driver_id` field
- `App\Models\Region` - Regions that drivers can serve
- `App\Models\Company` - Companies have region_id (pharmacies use this for driver assignment)

## Future Domain Services

This domain is prepared for future services:

### DeliveryAssignmentService (Planned)

**Purpose**: Handle driver assignment logic, route optimization, load balancing.

**Potential Features**:
- Automatic driver assignment based on location
- Load balancing across drivers
- Route optimization
- Delivery time estimation

**Usage Example** (Planned):
```php
use App\Domain\Deliveries\DeliveryAssignmentService;

$service = app(DeliveryAssignmentService::class);

// Auto-assign driver based on location and availability
$driver = $service->assignDriverToOrder(
    order: $order,
    criteria: [
        'max_distance' => 50, // km
        'max_load' => 10, // orders
        'preferred_driver' => $driverId // optional
    ]
);
```

### DeliveryTrackingService (Planned)

**Purpose**: Track delivery status, location, and provide real-time updates.

**Potential Features**:
- Real-time location tracking
- Delivery status updates
- Estimated delivery time
- Delivery confirmation
- Delivery proof (signatures, photos)

**Usage Example** (Planned):
```php
use App\Domain\Deliveries\DeliveryTrackingService;

$service = app(DeliveryTrackingService::class);

// Update delivery status
$delivery = $service->updateStatus(
    order: $order,
    status: 'in_transit',
    location: ['lat' => 24.7136, 'lng' => 46.6753],
    notes: 'On the way to buyer'
);

// Confirm delivery
$delivery = $service->confirmDelivery(
    order: $order,
    driver: $driver,
    proof: $signatureImage
);
```

### DeliveryRouteService (Planned)

**Purpose**: Optimize delivery routes for multiple orders.

**Potential Features**:
- Multi-order route optimization
- Distance calculation
- Time estimation
- Route visualization

**Usage Example** (Planned):
```php
use App\Domain\Deliveries\DeliveryRouteService;

$service = app(DeliveryRouteService::class);

// Optimize route for multiple orders
$route = $service->optimizeRoute(
    orders: [$order1, $order2, $order3],
    driver: $driver,
    startLocation: ['lat' => 24.7136, 'lng' => 46.6753]
);

// Returns optimized route with order sequence
// $route->orders = [$order2, $order1, $order3] // optimized sequence
// $route->totalDistance = 45.2 // km
// $route->estimatedTime = 120 // minutes
```

### DeliveryNotificationService (Planned)

**Purpose**: Send delivery notifications to buyers and suppliers.

**Potential Features**:
- Order assigned notifications
- Delivery started notifications
- Delivery status updates
- Delivery completed notifications

## Related Models

- `App\Models\User` - Drivers (users with `driver` role)
- `App\Models\Order` - Orders with driver assignment
- `App\Models\Company` - Supplier companies (drivers belong to suppliers)

## Integration Points

### With Orders Domain

Deliveries interact with orders:
- Orders are assigned to drivers
- Delivery status affects order status
- Delivery completion triggers order completion

### With Users Domain

Drivers are users:
- Drivers have `driver` role
- Drivers belong to supplier companies
- Driver authorization is company-scoped

### With Companies Domain

Drivers belong to suppliers:
- Drivers are managed by supplier companies
- Supplier-scoped driver operations
- Company-based driver filtering

## Business Rules (Current)

1. **Driver Assignment**:
   - Driver must belong to supplier company
   - Only suppliers/admins can assign drivers
   - Driver can be assigned to multiple orders
   - System checks if driver serves the pharmacy's region (warning logged if not, but assignment still allowed)
   - Suppliers can assign regions to their drivers via dashboard

2. **Driver Management**:
   - Suppliers can only manage their own drivers
   - Admins can manage all drivers
   - Drivers can view their assigned orders
   - Suppliers can assign multiple regions to each driver
   - Driver-region assignments can be updated anytime (e.g., weekly rotations)

3. **Authorization**:
   - Suppliers manage their drivers
   - Drivers view their own orders
   - Buyers cannot assign drivers

## Future Business Rules

1. **Delivery Assignment**:
   - Automatic assignment based on location
   - Load balancing across drivers
   - Driver availability checking

2. **Delivery Tracking**:
   - Real-time location updates
   - Status change notifications
   - Delivery proof requirements

3. **Delivery Routes**:
   - Route optimization for efficiency
   - Distance and time calculations
   - Multi-order route planning

## Constants

- `App\Constants\UserRole` - Includes `DRIVER` role constant

## Testing Considerations

- Test driver assignment
- Test driver authorization
- Test driver order listing
- Test multi-driver scenarios
- Test delivery status transitions (future)
- Test route optimization (future)
- Test location tracking (future)

## Current Location System

The system includes a hierarchical location management:

- **Cities**: Admin-managed cities (e.g., Damascus, Aleppo)
- **Regions**: Admin-managed regions belonging to cities (e.g., Mazzeh, Malki, Rawda in Damascus)
- **Companies**: Use `region_id` to specify their location
- **Drivers**: Can be assigned to multiple regions
- **Orders**: Include pharmacy region information for better driver assignment

### Driver-Region Assignment Flow

1. Admin creates cities and regions
2. Pharmacies select their region when registering/updating
3. Suppliers assign regions to their drivers
4. When pharmacy places order, supplier sees pharmacy's region
5. Supplier can assign driver who serves that region

## Future Enhancements

- Real-time GPS tracking
- Automatic driver assignment based on region and availability
- Delivery route optimization using coordinates
- Distance calculation between companies
- Delivery time windows
- Delivery proof (signatures, photos)
- Delivery notifications (SMS, push)
- Delivery analytics and reporting
- Driver performance metrics
- Delivery cost calculation based on distance
- Multi-stop delivery routes
- Delivery scheduling and time slots
- Geospatial queries for finding nearest suppliers/drivers

