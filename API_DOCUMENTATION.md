# API Documentation

## Swagger UI

The API documentation is available via Swagger UI:
- **URL**: `/api/documentation` (or `https://your-domain.com/api/documentation` in production)
- **JSON**: `/docs/api-docs.json`
- **Regenerate**: `php artisan l5-swagger:generate`

## Available Endpoints

### Authentication (`/api/v1/auth`)

| Method | Endpoint | Description | Auth Required |
|--------|----------|-------------|---------------|
| POST | `/register` | Register a new user | No |
| POST | `/login` | Login and get token | No |
| POST | `/logout` | Logout (revoke token) | Yes |
| GET | `/me` | Get authenticated user | Yes |

### Orders (`/api/v1/orders`)

| Method | Endpoint | Description | Auth Required | Role |
|--------|----------|-------------|---------------|------|
| GET | `/orders` | List orders | Yes | All |
| POST | `/orders` | Create order | Yes | Buyer |
| GET | `/orders/{id}` | Get order details | Yes | All |
| POST | `/orders/{id}/status` | Update order status | Yes | Supplier/Buyer |
| POST | `/orders/{id}/cancel` | Cancel order | Yes | Supplier/Buyer |

**Order Status Flow:**
- `pending` → `accepted` | `cancelled`
- `accepted` → `prepared` | `cancelled`
- `prepared` → `shipped` | `cancelled`
- `shipped` → `delivered` | `cancelled`

### Products (`/api/v1/products`)

| Method | Endpoint | Description | Auth Required | Role |
|--------|----------|-------------|---------------|------|
| GET | `/products` | List products | Yes | All |
| POST | `/products` | Create product | Yes | Supplier |
| GET | `/products/{id}` | Get product details | Yes | All |
| PUT | `/products/{id}` | Update product | Yes | Supplier |
| DELETE | `/products/{id}` | Delete product | Yes | Supplier |

### Companies (`/api/v1/companies`)

| Method | Endpoint | Description | Auth Required | Role |
|--------|----------|-------------|---------------|------|
| GET | `/companies` | List companies | Yes | All |
| POST | `/companies` | Create company | Yes | Admin |
| GET | `/companies/{id}` | Get company details | Yes | All |
| PUT | `/companies/{id}` | Update company | Yes | Admin |
| DELETE | `/companies/{id}` | Delete company | Yes | Admin |

## Authentication

All protected endpoints require a Bearer token in the Authorization header:

```
Authorization: Bearer {your-token}
```

Tokens are obtained via the `/api/v1/auth/login` or `/api/v1/auth/register` endpoints.

## Response Format

### Success Response
```json
{
    "success": true,
    "data": {...},
    "message": "Optional message"
}
```

### Error Response
```json
{
    "success": false,
    "error": "Error message",
    "errors": {...}
}
```

### Paginated Response
```json
{
    "success": true,
    "data": [...],
    "pagination": {
        "current_page": 1,
        "per_page": 15,
        "total": 100,
        "last_page": 7,
        "from": 1,
        "to": 15
    }
}
```

## Rate Limiting

- Login endpoint: 5 requests per minute
- Other endpoints: Default Laravel rate limiting

## Swagger Annotations

All endpoints are documented with OpenAPI 3.0 annotations. To regenerate documentation:

```bash
php artisan l5-swagger:generate
```

## Testing

You can test the API using:
1. Swagger UI at `/api/documentation`
2. Postman/Insomnia using the exported OpenAPI spec
3. cURL commands

## Example: Creating an Order

```bash
curl -X POST http://localhost:8000/api/v1/orders \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "supplier_company_id": 1,
    "items": [
      {
        "product_id": 1,
        "quantity": 10,
        "unit_price": 25.50
      }
    ],
    "notes": "Urgent delivery",
    "tax_rate": 15
  }'
```

