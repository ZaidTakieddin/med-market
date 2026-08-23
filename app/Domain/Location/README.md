# Location Domain

Admin-managed geographic reference data: cities and regions. Companies and drivers are
placed in regions; driver coverage is evaluated against the pharmacy's region.

## Domain Services

### LocationService (Implemented)

CRUD for `City` and `Region`: `createCity/updateCity/deleteCity` and
`createRegion/updateRegion/deleteRegion`. All writes are admin-only (enforced by
`CityPolicy`/`RegionPolicy` at the controller). Deletes are soft deletes.

## Notes

- Cities and regions are cached reference lists (`CacheableReference`) — writes flush the cache.
- Regions belong to a city; companies carry a `region_id`; the `driver_regions` pivot
  (with optional `day_of_week`) defines driver coverage — see the Deliveries domain.
