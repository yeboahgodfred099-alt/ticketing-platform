# Architecture — Secure Mobile Money Ticketing Platform

## 1. Architectural style

Use a modular monolith: one Laravel backend with clearly separated domain modules, one Next.js frontend application, PostgreSQL as the system of record and Redis for cache/queues/short-lived coordination.

Do not begin with microservices. Payment, order, inventory and ticket issuance require strong transactional coordination. A modular monolith is simpler to operate and safer for the initial product.

## 2. System context

```text
Customer browser ───────┐
Admin browser ──────────┼── HTTPS ── Edge/WAF ── Nginx
Gate staff browser ─────┘                         │
                                  ┌───────────────┴──────────────┐
                                  │                              │
                              Next.js                         Laravel API
                                                                 │
                                      ┌──────────────────────────┼────────────┐
                                      │                          │            │
                                  PostgreSQL                   Redis       Paystack
                                      │                       queues/cache  API/webhooks
                                      │                          │
                                  Backups                 Queue workers
```

Paystack sends webhooks to Laravel. The frontend must never receive or use the Paystack secret key.

## 3. Applications and boundaries

### Next.js frontend
Responsibilities:
- Public event pages and customer checkout.
- Admin dashboard UI.
- Staff scanning UI.
- Client-side presentation and basic validation.
- Calling versioned Laravel APIs.
- Displaying backend-calculated totals and payment states.

Must not:
- Decide authoritative prices or inventory.
- Mark payments successful.
- Generate authoritative tickets.
- Store Paystack secret keys.
- Authorize admin access based solely on frontend route guards.

### Laravel backend
Responsibilities:
- All business rules.
- Authentication and authorization.
- Event and ticket management.
- Orders and inventory reservations.
- Paystack charge initiation and verification.
- Webhook verification and processing.
- Ticket issuance and QR validation.
- Notifications, reporting and audit logs.

Use:
- Controllers for HTTP orchestration.
- Form Requests for validation.
- Services/actions for business operations.
- Policies/Gates for authorization.
- Events/listeners for internal decoupling.
- Jobs for asynchronous work.
- API Resources for controlled serialization.

### PostgreSQL
System of record for:
- Admins, roles and permissions.
- Events and ticket types.
- Orders, order items and reservations.
- Payment attempts and webhook event identifiers.
- Tickets and check-ins.
- Audit and notification records.

### Redis
Use for:
- Laravel queues.
- Cache.
- Rate-limit coordination where configured.
- Short-lived locks only where appropriate.

PostgreSQL constraints and transactions remain the ultimate integrity controls. Redis must not be the only protection against overselling or duplicate issuance.

## 4. Suggested Laravel module boundaries

- IdentityAccess: admins, staff, sessions, roles, permissions, MFA.
- Events: event lifecycle and public event queries.
- TicketCatalog: ticket types, sale windows and availability.
- Inventory: reservations, expiration and stock accounting.
- Orders: checkout, order items and order lifecycle.
- Payments: Paystack adapter, payment attempts, verification and reconciliation.
- Webhooks: signature validation, event persistence, deduplication and dispatch.
- Ticketing: ticket issuance, QR token generation and retrieval.
- CheckIn: event assignment, QR validation and check-in.
- Notifications: email and optional SMS.
- Reporting: sales and operational reporting.
- Audit: security and administrative audit records.

Use domain-oriented folders or Laravel's conventional folders consistently. Do not create a custom framework inside Laravel.

## 5. Critical workflows

### 5.1 Checkout and reservation
1. Validate event and ticket type are published/available.
2. Validate sale window and quantity limits.
3. Begin PostgreSQL transaction.
4. Lock ticket type/inventory row with `SELECT ... FOR UPDATE`.
5. Recalculate available quantity.
6. Create order and immutable order-item snapshots.
7. Create reservation with expiry.
8. Commit.
9. Initiate Paystack charge from backend.
10. Persist payment attempt and provider response after sanitizing sensitive fields.

Define failure handling if Paystack initiation fails after reservation creation. Release reservation safely or leave a short, explicit retry state; never leave inventory reserved indefinitely.

### 5.2 Payment success
1. Receive webhook or reconciliation result.
2. Verify webhook authenticity when processing webhook.
3. Fetch/verify transaction directly with Paystack.
4. Match transaction reference, amount, currency and expected order/payment.
5. Begin database transaction.
6. Lock relevant payment, order, reservation and inventory rows in a consistent lock order.
7. If already fulfilled, return idempotently.
8. Apply allowed payment state transition.
9. Convert reserved stock to sold stock.
10. Issue exactly one ticket per purchased admission.
11. Mark order paid and payment verified.
12. Commit.
13. Dispatch ticket email after commit.

Handle late success after reservation expiry explicitly. Do not oversell; move the case to reconciliation/manual resolution and follow the approved fulfillment/refund policy.

### 5.3 Webhook processing
- Capture raw body.
- Verify current Paystack signature scheme.
- Persist a unique event record safely.
- Return an appropriate response quickly.
- Process asynchronously.
- Verify transaction with Paystack.
- Apply idempotent state transition.
- Record processing outcome and errors without logging secrets.

Do not rely on webhook delivery order or assume only one event is delivered.

### 5.4 Reconciliation
Scheduled job identifies:
- Pending payments older than expected.
- Orders with successful verified payment but missing tickets.
- Payments whose amount/reference/currency do not match.
- Late success after reservation expiration.
- Webhook events stuck in processing.
- Reversals/refunds needing state updates.

Use Paystack verification and documented provider behavior. Reconciliation must be safe to rerun.

### 5.5 Ticket validation
1. Staff scans QR token.
2. Send token over HTTPS to Laravel.
3. Backend hashes/looks up token.
4. Check event assignment, event, ticket state and prior check-in.
5. Lock ticket row.
6. Create check-in and change ticket state atomically.
7. Return minimal result.

## 6. API conventions

Base path: `/api/v1`.

- JSON request/response.
- Consistent error envelope.
- Validation errors use appropriate 4xx status.
- Authentication/authorization failures use 401/403.
- Paginate list endpoints.
- Use request IDs/correlation IDs.
- Apply rate limits by route and risk.
- Use explicit API Resources.
- Never expose internal provider payloads without sanitization.
- Document endpoints with OpenAPI.

Suggested route groups:
- Public: `/events`, `/checkout`, `/payments/status`, `/tickets/retrieve`.
- Webhook: `/webhooks/paystack`.
- Admin: `/admin/*`.
- Staff: `/staff/*`.

## 7. Authentication architecture

Use Laravel Sanctum with secure stateful cookie-based sessions for the first-party Next.js admin/staff web applications, configured with exact trusted stateful domains and CSRF protection.

- MFA required for privileged accounts.
- Admin and staff accounts are separate from customers.
- Do not issue customer accounts.
- Session cookies: Secure, HttpOnly, appropriate SameSite.
- Regenerate session after authentication and privilege changes.
- Revoke sessions on logout, account disablement and credential compromise.
- Protect sensitive operations with reauthentication/MFA where appropriate.

## 8. Deployment topology

Recommended production services:
- Edge DNS/WAF/CDN.
- Nginx reverse proxy.
- Next.js server.
- Laravel PHP-FPM application.
- Queue worker process.
- Scheduler process.
- PostgreSQL (prefer managed or private isolated service).
- Redis (private).
- Private object storage for uploads.
- Offsite encrypted backup storage.
- Monitoring/logging.

Only Nginx/edge-facing ports should be public. PostgreSQL, Redis, queue and scheduler must not be publicly reachable.

## 9. Environment separation

Development:
- Local database and Redis.
- Paystack test keys.
- Synthetic data.
- Debugging permitted only locally.

Staging:
- Production-like configuration.
- Paystack test credentials.
- Restricted access.
- Full end-to-end testing.

Production:
- Live keys from secrets manager/environment injection.
- Debug disabled.
- HTTPS enforced.
- Restricted database roles.
- Monitoring and alerting.
- Automated backups and tested recovery.

## 10. Scalability and reliability

Start with a single modular backend deployment and separately scalable queue workers. Scale based on measured load.

Use:
- Database indexes based on query plans.
- Bounded pagination.
- Queue retries with backoff and dead-letter/failed-job monitoring.
- Timeouts on outbound HTTP requests.
- Circuit-breaker or controlled retry behavior for provider outages.
- Idempotent jobs.
- Health/readiness checks.
- Graceful deployment and migration strategy.

Do not promise capacity without load-test evidence.
