# Database Seeding Configuration

## Pooled vs Unpooled Connections

### Pooled Connection (Default)

-   **Endpoint**: `-pooler` suffix (e.g., `ep-xxx-pooler.c-2.eu-central-1.aws.neon.tech`)
-   **Use Case**: Production API requests, high concurrency scenarios
-   **Pros**:
    -   Better connection management for many concurrent requests
    -   Efficient resource usage
    -   Lower connection overhead
-   **Cons**:
    -   Doesn't handle transactions well during seeding operations
    -   Can cause "transaction aborted" errors with complex transactions

### Unpooled Connection (Seeder)

-   **Endpoint**: Direct connection without `-pooler` suffix (e.g., `ep-xxx.c-2.eu-central-1.aws.neon.tech`)
-   **Use Case**: Database seeding, migrations, complex transactions
-   **Pros**:
    -   Reliable transaction handling
    -   Better for operations that require multiple statements in a transaction
    -   No connection pooling overhead
-   **Cons**:
    -   Fewer concurrent connections available
    -   Higher connection overhead

## Configuration

### Environment Variables

Add these to your `.env` file for seeder configuration:

```env
# Default connection (pooled - for API)
DB_CONNECTION=pgsql
DB_HOST=ep-lingering-bush-ag3n7o6u-pooler.c-2.eu-central-1.aws.neon.tech
DB_PORT=5432
DB_DATABASE=neondb
DB_USERNAME=neondb_owner
DB_PASSWORD=your_password
DB_SSLMODE=require

# Seeder connection (unpooled - for seeding)
DB_SEEDER_HOST=ep-lingering-bush-ag3n7o6u.c-2.eu-central-1.aws.neon.tech
DB_SEEDER_PORT=5432
DB_SEEDER_DATABASE=neondb
DB_SEEDER_USERNAME=neondb_owner
DB_SEEDER_PASSWORD=your_password
```

**Note**: To get the unpooled connection string from Neon:

1. Go to your Neon project dashboard
2. Navigate to Connection Details
3. Look for "Direct connection" (not "Pooled connection")
4. Copy the host without the `-pooler` suffix

## Usage

The seeders automatically use the unpooled connection when configured. Simply run:

```bash
php artisan db:seed
```

Or seed individual seeders:

```bash
php artisan db:seed --class=OrderSeeder
```

## How It Works

1. `DatabaseSeeder` checks if `pgsql_seeder` connection is configured
2. If configured, it switches to the seeder connection before running seeders
3. After seeding completes, it restores the original connection
4. This ensures seeders use reliable transaction handling while API uses pooled connections
