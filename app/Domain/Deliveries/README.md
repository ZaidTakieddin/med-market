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

Handled by `App\Domain\Deliveries\DriverAssignmentService`; the endpoint
(`POST /orders/{id}/assign-driver`) stays thin: authorize via `OrderPolicy::assignDriver`
(supplier owning the order, or admin) → delegate → respond.

- Target must be a user with the driver role (`DomainException` → 422 otherwise)
- Driver must belong to the order's supplying company (admins may assign any driver)
- Serving the pharmacy's region is recommended but not enforced — mismatches are logged as warnings
- The assignment and its audit log entry happen inside a transaction

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

### Salesman Region Assignment

Salesmen (supplier-associated) can also be assigned to regions, similar to drivers:
- `salesman_regions` pivot table links salesmen to regions
- Salesmen can serve specific geographic areas
- Helps with territory management and client assignment

### Driver Work & Employment Fields

Drivers carry additional profile fields on the `users` table:

- `work_start_time` / `work_end_time` — daily shift hours (TIME, nullable)
- `employment_start_date` — date the driver started working (DATE, nullable)
- `employment_end_date` — driver's last day at work (DATE, nullable)
- `driver_status` — availability status, one of `App\Constants\DriverStatus`
  (`available`, `on_delivery`, `off`); defaults to `available`
- `region_plan_type` — `App\Constants\RegionPlanType` (`fixed` | `daily`); defaults to `fixed`
- `holidays` — JSON array of weekday names (`App\Constants\DayOfWeek`) the driver is off

### Driver Region Plans (fixed vs daily) & Holidays

A driver's region coverage is a **plan**, managed by
`App\Domain\Deliveries\DriverRegionPlanService` (controllers stay thin and delegate):

- **Fixed plan** (`region_plan_type = fixed`): the driver covers one or more regions
  **every working day**. Stored in `driver_regions` with `day_of_week = null`.
- **Daily plan** (`region_plan_type = daily`): the driver covers a **different region per
  weekday** (a weekly schedule). Stored in `driver_regions`, one row per weekday with
  `day_of_week` set. At most one region per weekday (`unique(driver_id, day_of_week)`).
- **Holidays** are weekdays the driver is off (`users.holidays`). A holiday weekday
  **cannot be assigned a region** in a daily plan; setting a weekday as a holiday
  automatically removes any region already assigned to it. Setting holidays uses the
  service so this invariant always holds.

The `driver_regions` pivot gained a nullable `day_of_week` column. The old
`unique(driver_id, region_id)` was replaced with `unique(driver_id, day_of_week)`
(Postgres treats NULLs as distinct, so fixed-plan rows stay unconstrained).

**Endpoints** (suppliers manage their own drivers; admins manage all):

- `GET /drivers/{id}/regions` — returns the plan: `{ planType, holidays, regions }`
  (fixed) or `{ planType, holidays, schedule }` (daily; `schedule` maps each weekday to
  a region or null).
- `POST /drivers/{id}/region-plan` — set the plan. Fixed: `{ plan_type: "fixed",
  region_ids: [...] }`. Daily: `{ plan_type: "daily", schedule: { saturday: 1, ... } }`.
  Assigning a holiday weekday is rejected (422).
- `PUT /drivers/{id}/holidays` — set holiday weekdays (`{ holidays: ["friday"] }`; empty
  array clears them).
- `POST /drivers/{id}/regions` — backward-compatible shortcut that sets a **fixed** plan
  from `region_ids`.
- `DELETE /drivers/{id}/regions/{region_id}` — detach a single region.

Two delivery counts are **computed live** (never stored) and surfaced on
`UserResource` for drivers via `withCount`/`loadCount` in `DriverController`:

- `deliveredOrdersCount` — assigned orders with status `delivered`
- `totalOrdersCount` — all orders assigned to the driver

> Migrations touching `users` must run on the unpooled `pgsql_seeder` connection
> (`php artisan migrate --database=pgsql_seeder`); Neon's pooled endpoint aborts DDL
> transactions.

### Models

- `App\Models\User` - Drivers are users with `driver` role, Salesmen are users with `salesman` role
  - `driverRegions()` - Belongs to many regions (via driver_regions pivot)
  - `salesmanRegions()` - Belongs to many regions (via salesman_regions pivot)
  - `salesmanClients()` - Has many salesman-pharmacy client assignments
  - `servesRegion(int $regionId)` - Check if driver serves a specific region
- `App\Models\Order` - Orders have `driver_id` and `placed_by_salesman_id` fields
- `App\Models\Region` - Regions that drivers and salesmen can serve
- `App\Models\Company` - Companies have region_id (pharmacies use this for driver/salesman assignment)
- `App\Models\SalesmanClient` - Tracks salesman-pharmacy client relationships

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

