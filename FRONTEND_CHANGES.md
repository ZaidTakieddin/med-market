# Frontend Integration Changes — Branch `fix/register-privilege-escalation`

This branch hardens security and fixes correctness bugs across auth, orders,
pricing, stock, and credit. Below are the changes the **web + mobile frontends
must adapt to** before this merges to `main`. Everything is under `/api/v1`.
Swagger (`/api/documentation`) has been regenerated.

> TL;DR for frontend: registration payload changed (breaking); order actions are
> now role-gated; shipped orders can't be cancelled; net-term orders enforce
> credit; cart checkout re-prices; the validation-error JSON shape is now
> consistent; 500s no longer include internal messages.

---

## 1. Registration `POST /auth/register` — ⚠️ BREAKING

**Before:** accepted `role` (any, incl. `admin`) and `company_id` (any existing company).
**Now:** public registration only creates a **buyer (pharmacy)** and **creates its own company**. `role` and `company_id` are ignored/removed.

**New request body:**
| field | required | notes |
|---|---|---|
| `name` | ✅ | user's name |
| `email` | ✅ | unique |
| `password` + `password_confirmation` | ✅ | min 8 |
| `company_name` | ✅ | **new** — the pharmacy/company name to create |
| `phone` | optional | user phone |
| `company_phone` | optional | |
| `company_address` | optional | |
| `region_id` | optional | must exist in `regions` |

- Remove any `role` / `company_id` fields from the signup form.
- Add a **company name** field (and optionally region picker).
- Endpoint is now **rate-limited** (5/min) → handle `429`.
- Supplier/driver/salesman/admin accounts are **not** self-registerable (created by admin/supplier flows).

## 2. Auth tokens now expire

Sanctum tokens expire (default **30 days**). Handle `401` by redirecting to login / refreshing the session. (Configurable via `SANCTUM_TOKEN_EXPIRATION_MINUTES`.)

## 3. Order status transitions `POST /orders/{id}/status` — behavioral

- Only the **supplier** that owns the order may advance it (`accepted` → `prepared` → `shipped` → `delivered`). Buyers/salesmen calling these get **403**.
- Buyers/salesmen may only **cancel** (while `pending` or `accepted`).
- **Frontend:** only show accept/prepare/ship/deliver actions to supplier users; show cancel to buyers per the rules below.

## 4. A shipped order can no longer be cancelled — behavioral

Valid transitions: `pending→accepted|cancelled`, `accepted→prepared|cancelled`, `prepared→shipped|cancelled`, `shipped→delivered`, `delivered`/`cancelled` final. Attempting `shipped→cancelled` returns **400**. **Frontend:** hide/disable "Cancel" once an order is `shipped`.

## 5. Net-term orders enforce trade credit — behavioral

When placing an order with `payment_method` of `net_15`/`net_30`/`net_60` (via `POST /orders` or `POST /salesmen/{id}/place-order`):
- Requires an **approved** buyer–supplier relationship with **available credit**.
- If none/insufficient → **400** with a message (`No approved credit relationship…` or `Order exceeds available credit…`).
- `cash` orders are unaffected.
- **Frontend:** surface these 400s on checkout; offer cash as fallback; the buyer's balance increases on order and is released on cancel/payment.

## 6. Cart checkout re-prices at checkout — behavioral

`POST /cart/checkout` and `POST /cart/checkout-supplier` now **re-resolve prices at checkout** using current tier prices (not the price captured when the item was added). The order total may differ from the cart display if prices changed in the meantime. Checkout also returns **403** if a supplier in the cart is not visible to the buyer. **Frontend:** re-fetch/confirm the cart total at the checkout step; handle the 403.

## 7. `POST /orders` — `placed_by_salesman_id` ignored

For salesman users, the order is always attributed to the authenticated salesman (the client-supplied `placed_by_salesman_id` is ignored — it was a spoofing vector). Use `POST /salesmen/{id}/place-order` for on-behalf ordering.

---

## 8. Error response contract — now consistent ✅

**Validation errors (422)** always use the nested shape (previously the Form Request path returned a flat `error: "Validation failed"` string):

```json
{
  "success": false,
  "error": { "title": "Validation failed", "message": "The given data was invalid." },
  "errors": { "email": ["This email is already registered."] }
}
```

**Other errors** (`400/401/403/404/409/500`):

```json
{ "success": false, "error": { "title": "Forbidden", "message": "…" } }
```

- Parse `error.title` / `error.message` (object), and `errors` (field → messages) for 422.
- **`500` responses no longer include internal exception text** — they return a generic message. Do not surface raw server errors.

Success and pagination shapes are unchanged (`{ success, data, pagination? }`, camelCase pagination keys).

---

## Internal changes (no frontend impact)

State-machine consolidation, stock oversell row-locking, pricing `resolve`/`resolveMany` parity, model factories, cross-DB test database, CI pipeline, and a repo-wide Pint normalization. These don't change the API contract.

## Still pending (internal only — won't change the contract)

- Extract cart checkout into a domain `CheckoutService` (refactor of the large CartController).
- Standardize remaining authorization onto Policies (currently enforced via inline role checks).
- Caching for read-heavy list endpoints + adopt batch pricing.
