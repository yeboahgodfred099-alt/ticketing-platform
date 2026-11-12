# AGENTS.md — Development Instructions for AI Coding Agents

## 1. Mission

Build and maintain a secure, production-ready event ticketing platform for Ghana. Customers purchase tickets without creating accounts. Payments are processed through Paystack Ghana Mobile Money. Administrators manage events, ticket types, orders, payments, attendees and ticket validation.

This file is the highest-level project instruction. Read it before making changes. Then read the relevant documents in `docs/`. If instructions conflict, follow this order:

1. Security and payment-integrity requirements in `docs/SECURITY_REQUIREMENTS.md`.
2. Product requirements in `docs/PROJECT_SPECIFICATION.md`.
3. Architecture constraints in `docs/ARCHITECTURE.md`.
4. Database constraints in `docs/DATABASE_DESIGN.md`.
5. Workflow in `docs/DEVELOPMENT_WORKFLOW.md`.

Do not silently weaken a requirement. Raise a clear question when a requirement is ambiguous or contradictory.

## 2. Mandatory technology choices

- Frontend: Next.js, React, TypeScript, Tailwind CSS, shadcn/ui and Radix UI.
- Backend: Laravel REST API.
- Database: PostgreSQL.
- Cache, queues and distributed locks: Redis.
- Payments: Paystack Ghana Mobile Money.
- Deployment: Docker Compose, Nginx and PHP-FPM; production may use managed PostgreSQL/Redis.
- Monitoring: error monitoring plus centralized operational logs.
- QR generation: a maintained QR-code library; QR payloads must contain random opaque tokens, not personal or payment data.

Do not replace these technologies without explicit approval.

## 3. Non-negotiable product rules

1. Customers must not be required to register, log in or create passwords.
2. Admin and staff access must be authenticated and authorized.
3. Laravel is the authority for pricing, inventory, order state, payment state, ticket issuance and check-in.
4. Never trust frontend payment success, redirects, screenshots or client-submitted totals.
5. Never issue a ticket until the backend has independently verified a successful Paystack transaction and matched the reference, amount and currency to the intended order/payment.
6. Never collect or store a Mobile Money PIN.
7. Do not persist OTP values. Only implement OTP continuation when the current Paystack API response and approved payment flow require it.
8. Duplicate webhooks, retries and repeated requests must not duplicate charges, ticket issuance or check-ins.
9. Inventory must never be oversold, including under concurrent checkout.
10. Financial records must not be casually deleted or rewritten. Use controlled, auditable state transitions and adjustments.
11. A customer must not be able to retrieve another customer's ticket merely by guessing an order number or UUID.
12. Every sensitive administrative action must be auditable.

## 4. Engineering rules

- Use small, cohesive modules and services. Keep controllers thin.
- Use Laravel Form Requests for validation, Policies/Gates for authorization, API Resources for response shaping, and database transactions for critical state changes.
- Use TypeScript strict mode. Avoid `any`; explain and isolate any justified exception.
- Use database migrations for all schema changes. Never make undocumented manual production schema changes.
- Use Eloquent or parameterized queries. Never concatenate untrusted input into SQL.
- Use explicit status enums/constants and documented state transitions.
- Use integer minor currency units (pesewas for GHS); never use floating-point arithmetic for money.
- Use UTC timestamps in storage and display event-local time correctly.
- Keep secrets out of source control, frontend bundles, logs, screenshots and error messages.
- Do not put Paystack secret keys in Next.js or browser code.
- Do not log passwords, OTPs, PINs, session cookies, authorization headers, full secrets or raw QR tokens.
- Do not add dependencies without checking maintenance, compatibility and security.
- Pin production container/image versions; do not use floating `latest` tags in production.
- Keep public API responses minimal; never serialize database models blindly.

## 5. Payment implementation rules

Before coding payment integration, inspect the current official Paystack documentation and confirm the current Ghana Mobile Money flow, channel/provider codes, response actions, webhook signature header, verification requirements, supported currencies and amount units. Do not rely on memory or old tutorials.

Implement payment initiation and all continuation steps server-side. Handle asynchronous states such as pending or action-required. A browser return URL is for user experience only, not proof of payment.

Webhook processing must:
- Read the exact raw request body.
- Verify the signature using the current Paystack-documented method and configured secret.
- Reject invalid signatures.
- Persist/deduplicate the event safely.
- Acknowledge promptly after safe receipt, then process asynchronously where appropriate.
- Verify the transaction with Paystack before fulfilling the order.
- Be idempotent and safe under concurrent delivery.
- Avoid assuming webhook delivery order.

Never assume all Ghanaian networks use an identical OTP/PIN flow. Follow the actual Paystack response and current documentation. Never build a custom form asking customers to enter their Mobile Money PIN.

## 6. Critical transaction rules

For inventory reservation:
- Lock the relevant ticket-type row in a database transaction.
- Recalculate available inventory from authoritative values.
- Reject insufficient stock.
- Create the order, order items and reservation atomically.
- Expire reservations through a scheduled backend job, not browser timers alone.

For successful payment:
- Lock payment, order, reservation and inventory records in a consistent order.
- Verify payment identity, amount, currency and status.
- Convert reserved quantity to sold quantity.
- Create exactly the expected number of tickets.
- Mark payment/order state consistently.
- Commit as one database transaction.
- Dispatch notifications only after commit.

For check-in:
- Validate token server-side.
- Lock the ticket row.
- Verify event, status and prior check-in.
- Record check-in and mark ticket used atomically.

## 7. Security baseline

Use OWASP ASVS Level 2 as the verification baseline, adjusted by a documented risk assessment. Apply least privilege, secure defaults and defense in depth.

At minimum:
- Secure admin/staff authentication and MFA.
- Strong password hashing.
- Secure, HttpOnly, appropriately SameSite cookies.
- CSRF protection for cookie-authenticated requests.
- Strict CORS allowlist.
- Rate limits and abuse controls on public checkout, retrieval and authentication.
- Authorization on every protected resource.
- Output encoding and safe rich-text handling.
- Content Security Policy and security headers.
- HTTPS everywhere.
- Private database and Redis networking.
- Separate database migration and runtime privileges.
- Encrypted backups and tested restoration.
- Dependency, container and code vulnerability scanning.
- Audit logs and monitoring.
- No secrets or sensitive authentication data in logs.

Do not claim the system is secure merely because these controls are listed. Implement, test and document them.

## 8. Frontend rules

- Build a polished, accessible, responsive commercial ticketing experience.
- Use reusable components and a consistent design system.
- Include loading, empty, validation, pending, failure, success and unavailable states.
- Make payment status pages resilient to refreshes and browser closure.
- Poll only with bounded intervals/backoff, or use a suitable server-driven update mechanism.
- Do not expose admin screens or sensitive data to public routes.
- Treat all client input as untrusted.
- Never calculate authoritative totals only in the browser.

## 9. Testing and definition of done

A feature is not complete until:
- It is implemented end-to-end.
- Validation and authorization are tested.
- Relevant automated tests pass.
- Failure and retry paths are tested.
- Critical database constraints are present.
- Logs contain useful diagnostic information without secrets.
- Documentation is updated.

Required test categories:
- Unit and feature tests.
- Payment provider integration tests using test credentials/sandbox.
- Webhook signature, replay and duplicate-delivery tests.
- Inventory concurrency tests.
- Duplicate ticket issuance tests.
- QR double-scan concurrency tests.
- Authorization/IDOR tests.
- Rate-limit and input-validation tests.
- Backup restoration test.
- Staging acceptance and load tests.

Do not use real customer money or production credentials for automated tests.

## 10. Working procedure

Before each task:
1. Read this file and relevant docs.
2. Inspect the existing repository and identify the current framework versions and conventions.
3. Summarize the intended change and files affected.
4. Implement the smallest complete vertical slice.
5. Run formatting, static analysis and relevant tests.
6. Report what changed, commands/tests run, results, known limitations and next steps.

Do not overwrite existing work blindly. Do not invent credentials, provider responses, completed tests or deployment status. If the repository already contains code, inspect it before restructuring.
