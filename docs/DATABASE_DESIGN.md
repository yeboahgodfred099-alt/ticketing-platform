# Database Design — PostgreSQL

## 1. General conventions

- PostgreSQL is the authoritative data store.
- Use UUID primary keys for externally referenced records.
- Use `timestamptz` for instants; store UTC and display `Africa/Accra` for Ghana event times.
- Use integer minor units for money. GHS 100.00 = 10000 pesewas.
- Never use floating-point columns for money.
- Use foreign keys, unique constraints, check constraints and indexes.
- Use migrations for every schema change.
- Avoid hard deletion of financial, payment, ticket and audit records.
- Use explicit status values and documented state transitions.
- Keep personal data minimal and access-controlled.

Names below are logical design guidance. The implementation may adjust exact column types/names while preserving constraints and behavior.

## 2. Entity relationships

```text
admins ──< admin_roles >── roles ──< role_permissions >── permissions
admins ──< audit_logs
admins ──< events (created_by)

events ──< ticket_types
events ──< orders
orders ──< order_items >── ticket_types
orders ──< payments
payments ──< webhook_events
orders ──< inventory_reservations
order_items ──< tickets
events ──< tickets
tickets ──< check_ins
admins ──< check_ins
```

## 3. Tables

### admins
- `id UUID PRIMARY KEY`
- `name VARCHAR(150) NOT NULL`
- `email VARCHAR(255) NOT NULL UNIQUE`
- `password VARCHAR(255) NOT NULL`
- `is_active BOOLEAN NOT NULL DEFAULT TRUE`
- `email_verified_at TIMESTAMPTZ NULL`
- `last_login_at TIMESTAMPTZ NULL`
- standard timestamps

Do not store plaintext passwords. Store MFA secrets/recovery codes using an appropriate encrypted-at-rest design or vetted MFA package. Do not log them.

### roles
- `id UUID PRIMARY KEY`
- `name VARCHAR(80) UNIQUE`
- `description TEXT NULL`

### permissions
- `id UUID PRIMARY KEY`
- `name VARCHAR(120) UNIQUE`
- `description TEXT NULL`

### admin_roles
- `admin_id UUID FK admins`
- `role_id UUID FK roles`
- composite primary key `(admin_id, role_id)`

### role_permissions
- `role_id UUID FK roles`
- `permission_id UUID FK permissions`
- composite primary key `(role_id, permission_id)`

### events
- `id UUID PRIMARY KEY`
- `name VARCHAR(255) NOT NULL`
- `slug VARCHAR(255) UNIQUE NOT NULL`
- `description TEXT NULL`
- `banner_path TEXT NULL`
- `venue_name VARCHAR(255) NULL`
- `venue_address TEXT NULL`
- `starts_at TIMESTAMPTZ NOT NULL`
- `ends_at TIMESTAMPTZ NOT NULL`
- `sales_starts_at TIMESTAMPTZ NULL`
- `sales_ends_at TIMESTAMPTZ NULL`
- `timezone VARCHAR(64) NOT NULL DEFAULT 'Africa/Accra'`
- `status` (draft/published/unpublished/cancelled/archived)
- `created_by UUID FK admins`
- timestamps

Add checks such as `ends_at > starts_at`. Validate sale windows.

### ticket_types
- `id UUID PRIMARY KEY`
- `event_id UUID FK events`
- `name VARCHAR(150) NOT NULL`
- `description TEXT NULL`
- `price_minor BIGINT NOT NULL CHECK (price_minor >= 0)`
- `currency CHAR(3) NOT NULL DEFAULT 'GHS'`
- `quantity_total INTEGER NOT NULL CHECK (quantity_total >= 0)`
- `quantity_sold INTEGER NOT NULL DEFAULT 0 CHECK (quantity_sold >= 0)`
- `min_per_order INTEGER NOT NULL DEFAULT 1 CHECK (min_per_order > 0)`
- `max_per_order INTEGER NULL`
- `sales_starts_at TIMESTAMPTZ NULL`
- `sales_ends_at TIMESTAMPTZ NULL`
- `status` (active/inactive/archived)
- timestamps

Prefer calculating active reservations from reservation rows or maintaining a carefully reconciled counter. If `quantity_reserved` is denormalized, update it only transactionally and add reconciliation checks. Enforce that sold plus active reserved never exceeds total.

### orders
- `id UUID PRIMARY KEY`
- `order_number VARCHAR(50) UNIQUE NOT NULL`
- `event_id UUID FK events`
- `customer_name VARCHAR(255) NOT NULL`
- `customer_email VARCHAR(255) NOT NULL`
- `customer_phone VARCHAR(30) NOT NULL`
- `subtotal_minor BIGINT NOT NULL CHECK (subtotal_minor >= 0)`
- `discount_minor BIGINT NOT NULL DEFAULT 0 CHECK (discount_minor >= 0)`
- `total_amount_minor BIGINT NOT NULL CHECK (total_amount_minor >= 0)`
- `currency CHAR(3) NOT NULL`
- `status` (pending_payment/paid/expired/cancelled/partially_refunded/refunded)
- `expires_at TIMESTAMPTZ NULL`
- `paid_at TIMESTAMPTZ NULL`
- timestamps

Do not use order number as an authentication secret. Avoid storing unnecessary payment card/mobile money credentials.

### order_items
- `id UUID PRIMARY KEY`
- `order_id UUID FK orders`
- `ticket_type_id UUID FK ticket_types`
- `ticket_name_snapshot VARCHAR(150) NOT NULL`
- `quantity INTEGER NOT NULL CHECK (quantity > 0)`
- `unit_price_minor BIGINT NOT NULL CHECK (unit_price_minor >= 0)`
- `total_minor BIGINT NOT NULL CHECK (total_minor >= 0)`
- `currency CHAR(3) NOT NULL`
- timestamps

Snapshot ticket name, unit price and currency at purchase time.

### inventory_reservations
- `id UUID PRIMARY KEY`
- `order_id UUID FK orders`
- `ticket_type_id UUID FK ticket_types`
- `quantity INTEGER NOT NULL CHECK (quantity > 0)`
- `status` (active/converted/released/expired)
- `expires_at TIMESTAMPTZ NOT NULL`
- `converted_at TIMESTAMPTZ NULL`
- `released_at TIMESTAMPTZ NULL`
- timestamps

Index active reservations by ticket type and expiry. Define whether an order has one reservation per ticket type and enforce an appropriate unique constraint.

### payments
- `id UUID PRIMARY KEY`
- `order_id UUID FK orders`
- `reference VARCHAR(100) UNIQUE NOT NULL`
- `provider VARCHAR(30) NOT NULL` (paystack)
- `provider_transaction_id VARCHAR(100) NULL`
- `channel VARCHAR(50) NULL`
- `mobile_money_provider VARCHAR(30) NULL`
- `customer_phone VARCHAR(30) NULL`
- `amount_minor BIGINT NOT NULL CHECK (amount_minor >= 0)`
- `currency CHAR(3) NOT NULL`
- `status` (initiated/pending/requires_action/success/failed/abandoned/expired/reversed/refunded)
- `provider_response_sanitized JSONB NULL`
- `failure_reason TEXT NULL`
- `verified_at TIMESTAMPTZ NULL`
- timestamps

Add a unique constraint on provider transaction ID where non-null if provider semantics support it. Do not persist PIN or OTP. Sanitize provider responses before storage.

### webhook_events
- `id UUID PRIMARY KEY`
- `provider VARCHAR(30) NOT NULL`
- `event_identifier VARCHAR(255) NOT NULL`
- `event_type VARCHAR(100) NOT NULL`
- `payment_id UUID NULL FK payments`
- `payload_hash VARCHAR(128) NOT NULL`
- `processing_status` (received/queued/processing/processed/failed/ignored)
- `received_at TIMESTAMPTZ NOT NULL`
- `processed_at TIMESTAMPTZ NULL`
- `last_error_code VARCHAR(100) NULL`
- `UNIQUE(provider, event_identifier)`

Do not assume every webhook payload has a stable unique event ID. If it does not, define a safe deduplication key based on documented provider data and transaction identity. Store only sanitized/minimized payload data if retention is justified.

### tickets
- `id UUID PRIMARY KEY`
- `order_item_id UUID FK order_items`
- `event_id UUID FK events`
- `ticket_number VARCHAR(100) UNIQUE NOT NULL`
- `qr_token_hash VARCHAR(255) UNIQUE NOT NULL`
- `holder_name VARCHAR(255) NOT NULL`
- `holder_email VARCHAR(255) NULL`
- `status` (active/checked_in/cancelled/refunded)
- `issued_at TIMESTAMPTZ NOT NULL`
- `used_at TIMESTAMPTZ NULL`
- timestamps

Store only a hash of the random QR token. The original token is delivered to the customer and must not be logged. If the token must be re-delivered, design a secure regeneration/rotation flow rather than storing plaintext indefinitely.

### check_ins
- `id UUID PRIMARY KEY`
- `ticket_id UUID FK tickets`
- `admin_id UUID FK admins`
- `checked_in_at TIMESTAMPTZ NOT NULL`
- `gate_identifier VARCHAR(100) NULL`
- `UNIQUE(ticket_id)` for single admission/check-in

Use a transaction and row lock to create check-in and update ticket state atomically.

### audit_logs
- `id UUID PRIMARY KEY`
- `admin_id UUID NULL FK admins`
- `action VARCHAR(150) NOT NULL`
- `target_type VARCHAR(100) NULL`
- `target_id UUID NULL`
- `ip_address INET NULL`
- `user_agent TEXT NULL`
- `metadata JSONB NULL`
- `created_at TIMESTAMPTZ NOT NULL`

Never store secrets or sensitive authentication data in metadata. Restrict updates/deletes.

### notification_logs
- `id UUID PRIMARY KEY`
- `order_id UUID NULL FK orders`
- `ticket_id UUID NULL FK tickets`
- `channel VARCHAR(30) NOT NULL`
- `recipient_masked VARCHAR(255) NULL`
- `template_key VARCHAR(100) NOT NULL`
- `status` (queued/sent/failed)
- `provider_message_id VARCHAR(150) NULL`
- `attempt_count INTEGER NOT NULL DEFAULT 0`
- timestamps

### system_settings
Use only for non-secret operational settings. Keep API keys and secrets in a secret manager/environment injection, not a general settings table.

## 4. Required indexes and constraints

At minimum:
- Unique event slug.
- Unique order number.
- Unique payment reference.
- Unique ticket number.
- Unique QR token hash.
- Unique provider webhook deduplication key.
- Unique check-in per ticket.
- Index orders by event/status/created time.
- Index payments by order/status/reference.
- Index tickets by event/status.
- Index reservations by status/expiry/ticket type.
- Index webhook events by processing status/received time.

Add check constraints for positive quantities and non-negative amounts. Use foreign key delete restrictions for financial records and tickets. Prefer archive/cancel state transitions.

## 5. Money and calculations

- Store `amount_minor` as integer.
- GHS uses pesewas.
- Do not use floating-point math.
- Convert to provider API units exactly as required by current Paystack documentation.
- Persist the expected amount and currency on the payment attempt.
- Compare verified provider amount and currency exactly to the expected payment record.

## 6. Transaction and locking requirements

- Use PostgreSQL transactions for reservation, payment fulfillment and check-in.
- Use row-level locks for ticket inventory, payment/order fulfillment and ticket check-in.
- Keep lock acquisition order consistent to reduce deadlocks.
- Retry safe deadlock/serialization failures with bounded retries.
- Add unique constraints as a final defense against duplicate fulfillment.
- Never depend only on an application-level “if not exists” check without a database constraint.

## 7. Retention and privacy

Define retention periods with legal review. Restrict access to customer personal data. Mask phone/email in broad admin lists where practical. Delete or anonymize personal data only where legally and operationally permitted; retain required financial/audit records according to applicable law and policy.
