# What Was Wrong
- The `client_token` column was nullable, but according to the API description, it should always be provided, so it should be required.
- `client_token` was not unique. Adding a database-level unique constraint ensures a **last line of defense** for idempotency.
- There was a potential **N+1 query problem** when fetching products inside a loop.
- Database transactions were not used, which risks **partial orders** if any step fails.
- No locking was implemented, which could lead to **race conditions** during concurrent requests.

# What You Changed and Why
- Made `client_token` required and added a unique constraint to ensure proper idempotency.
- Performed **early return** if any requested product does not exist in the database, avoiding processing invalid data.
- Loaded all required products upfront to avoid N+1 queries.
- Wrapped the order creation and stock decrement inside a **database transaction** to maintain atomicity.
- Used **pessimistic locking (`lockForUpdate`)** to prevent race conditions and ensure stock consistency.

# How Your Approach Handles Concurrency
- Pessimistic locking ensures that only one process can decrement stock for the same products at a time, preventing race conditions.

# Trade-offs / Alternatives Considered
- Could explore optimistic locking or queue-based order processing for scalability, but for now, this approach balances safety and simplicity.
- Move the validation to its own class

# Notes
- It is unclear whether the client can send duplicate `product_id` in the same order. To handle this safely, I grouped items by `product_id` and summed their quantities before inserting into the order items table.

# Body Request
```JSON
{
  "user_id": 1,
  "client_token": "tok_ABC123AAAAAA",
  "items": [
    {
      "product_id": 1,
      "qty": 1
    },
    {
      "product_id": 2,
      "qty": 1
    }
  ]
}
```