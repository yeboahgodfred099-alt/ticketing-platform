# Implementation Plan — Secure Mobile Money Ticketing Platform

> **Status**: Approved  
> **Created**: 2026-11-12  
> **Domain**: ticket.clevawave.com  
> **Stack**: Laravel 11 + Next.js 15 + PostgreSQL 16 + Redis 7  
> **Payment**: Paystack Ghana Mobile Money (Charge API)

---

## 1. Project Overview

A multi-event ticketing platform for Ghana. Customers purchase tickets without creating accounts. Payments are processed through Paystack Ghana Mobile Money. Administrators manage events, ticket types, orders, payments, attendees and ticket validation.

### Core Principles
- Guest checkout only — no customer accounts or passwords
- Tickets issued only after independent server-side payment verification
- Modular monolith architecture — strong transactional coordination
- OWASP ASVS Level 2 security baseline
- Integer minor currency units (pesewas) — no floating-point money
- UTC storage, Africa/Accra display

---

## 2. Resolved Design Decisions

| Decision | Resolution | Rationale |
|---|---|---|
| **Super Admin bootstrap** | Artisan command (`php artisan admin:create-super`) with interactive secure prompts. No self-registration. No seeder with hardcoded credentials. | Prevents exposure of admin credentials in source control. Command requires confirmation, validates password strength, and forces MFA setup on first login. |
| **Paystack fees** | Absorbed by organizer. Customer sees only the ticket price. Fee is never shown, itemized, or added to the customer total. | Business decision confirmed by stakeholder. Backend records Paystack fee from verification response for organizer reporting. |
| **Domain** | `ticket.clevawave.com` | Sanctum stateful domain, CORS allowlist, session cookie domain, and CSP all configured for this domain. |
| **Paystack credentials** | Test keys available. Secret key stored in `.env` (backend only). Public key used only if Paystack Popup is needed (not planned for MoMo Charge API flow). | Per AGENTS.md §4–5: secret key never in frontend. |
| **Ticket retrieval auth** | High-entropy secret access token in URL (sent via email). Rate-limited. No OTP in v1; OTP recovery designed as extension point. | Simplest secure approach for v1. OTP adds phone verification dependency. |
| **Refund workflow** | State tracking only in v1. No automated refund initiation. Manual refunds processed externally on Paystack dashboard; state updated via webhook or reconciliation. | Per PROJECT_SPECIFICATION.md §3: automated refunds out of scope without explicit approval. |
| **File storage** | Local disk in development. S3-compatible object storage in production. | Configured via Laravel filesystem disk. |
| **SMS notifications** | Not implemented in v1. Extension point in NotificationService. Email only. | Reduces v1 complexity. Architecture supports adding SMS channel later. |

---

## 3. Technology Stack

| Layer | Technology | Version (Pinned) |
|---|---|---|
| Frontend | Next.js, React, TypeScript (strict) | Next.js 15.x, React 19.x |
| UI Framework | Tailwind CSS, shadcn/ui, Radix UI | Tailwind 4.x |
| Backend | Laravel, PHP | Laravel 11.x, PHP 8.3 |
| Database | PostgreSQL | 16.x |
| Cache / Queue | Redis | 7.x |
| Payments | Paystack Charge API (Ghana Mobile Money) | Current API |
| QR Generation | bacon/bacon-qr-code (Laravel) | Latest stable |
| QR Scanning | html5-qrcode (Frontend) | Latest stable |
| MFA | pragmarx/google2fa-laravel | Latest stable |
| Deployment | Docker Compose, Nginx, PHP-FPM | Pinned versions |
| Monitoring | Sentry (Laravel + Next.js) | Latest stable |

---

## 4. Project Structure

```
ticketing-platform/
├── AGENTS.md
├── implementation.md                    ← This file
├── docs/
│   ├── PROJECT_SPECIFICATION.md
│   ├── ARCHITECTURE.md
│   ├── DATABASE_DESIGN.md
│   ├── SECURITY_REQUIREMENTS.md
│   ├── DEVELOPMENT_WORKFLOW.md
│   └── api/                             ← OpenAPI specs (generated)
│
├── backend/                             ← Laravel 11 application
│   ├── app/
│   │   ├── Enums/
│   │   │   ├── EventStatus.php          ← draft|published|unpublished|cancelled|archived
│   │   │   ├── OrderStatus.php          ← pending_payment|paid|expired|cancelled|partially_refunded|refunded
│   │   │   ├── PaymentStatus.php        ← initiated|pending|requires_action|success|failed|abandoned|expired|reversed|refunded
│   │   │   ├── TicketStatus.php         ← active|checked_in|cancelled|refunded
│   │   │   ├── TicketTypeStatus.php     ← active|inactive|archived
│   │   │   ├── ReservationStatus.php    ← active|converted|released|expired
│   │   │   ├── WebhookStatus.php        ← received|queued|processing|processed|failed|ignored
│   │   │   └── NotificationStatus.php   ← queued|sent|failed
│   │   │
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   │   ├── Api/V1/
│   │   │   │   │   ├── Public/
│   │   │   │   │   │   ├── EventController.php
│   │   │   │   │   │   ├── CheckoutController.php
│   │   │   │   │   │   ├── PaymentStatusController.php
│   │   │   │   │   │   └── TicketRetrievalController.php
│   │   │   │   │   ├── Admin/
│   │   │   │   │   │   ├── DashboardController.php
│   │   │   │   │   │   ├── EventController.php
│   │   │   │   │   │   ├── TicketTypeController.php
│   │   │   │   │   │   ├── OrderController.php
│   │   │   │   │   │   ├── PaymentController.php
│   │   │   │   │   │   ├── AttendeeController.php
│   │   │   │   │   │   ├── ReportController.php
│   │   │   │   │   │   ├── AuditLogController.php
│   │   │   │   │   │   ├── AdminController.php
│   │   │   │   │   │   └── RoleController.php
│   │   │   │   │   ├── Staff/
│   │   │   │   │   │   └── CheckInController.php
│   │   │   │   │   └── Webhook/
│   │   │   │   │       └── PaystackWebhookController.php
│   │   │   │   └── Auth/
│   │   │   │       ├── LoginController.php
│   │   │   │       ├── MfaController.php
│   │   │   │       └── LogoutController.php
│   │   │   │
│   │   │   ├── Middleware/
│   │   │   │   ├── EnsureMfaVerified.php
│   │   │   │   ├── ForceHttps.php
│   │   │   │   ├── AttachRequestId.php
│   │   │   │   └── CaptureRawBody.php   ← For webhook signature verification
│   │   │   │
│   │   │   ├── Requests/               ← One Form Request per endpoint
│   │   │   │   ├── Auth/
│   │   │   │   ├── Admin/
│   │   │   │   ├── Public/
│   │   │   │   └── Staff/
│   │   │   │
│   │   │   └── Resources/              ← API Resources per entity
│   │   │       ├── EventResource.php
│   │   │       ├── EventCollection.php
│   │   │       ├── TicketTypeResource.php
│   │   │       ├── OrderResource.php
│   │   │       ├── PaymentResource.php
│   │   │       ├── TicketResource.php
│   │   │       └── ...
│   │   │
│   │   ├── Models/
│   │   │   ├── Admin.php
│   │   │   ├── Role.php
│   │   │   ├── Permission.php
│   │   │   ├── Event.php
│   │   │   ├── TicketType.php
│   │   │   ├── Order.php
│   │   │   ├── OrderItem.php
│   │   │   ├── InventoryReservation.php
│   │   │   ├── Payment.php
│   │   │   ├── WebhookEvent.php
│   │   │   ├── Ticket.php
│   │   │   ├── CheckIn.php
│   │   │   ├── AuditLog.php
│   │   │   ├── NotificationLog.php
│   │   │   └── SystemSetting.php
│   │   │
│   │   ├── Policies/
│   │   │   ├── EventPolicy.php
│   │   │   ├── TicketTypePolicy.php
│   │   │   ├── OrderPolicy.php
│   │   │   ├── PaymentPolicy.php
│   │   │   ├── AdminPolicy.php
│   │   │   ├── RolePolicy.php
│   │   │   ├── ReportPolicy.php
│   │   │   └── AuditLogPolicy.php
│   │   │
│   │   ├── Services/
│   │   │   ├── Checkout/
│   │   │   │   ├── CheckoutService.php       ← Orchestrates order + reservation + payment initiation
│   │   │   │   └── InventoryService.php      ← Atomic reservation, release, conversion
│   │   │   ├── Payment/
│   │   │   │   ├── PaystackAdapter.php       ← HTTP client for Paystack API
│   │   │   │   ├── PaymentService.php        ← Payment state management
│   │   │   │   ├── WebhookProcessor.php      ← Signature verification, dedup, dispatch
│   │   │   │   └── ReconciliationService.php ← Scheduled verification of pending payments
│   │   │   ├── Ticketing/
│   │   │   │   ├── TicketIssuanceService.php ← Atomic ticket creation post-payment
│   │   │   │   └── QrTokenService.php        ← Crypto random generation, hashing
│   │   │   ├── CheckIn/
│   │   │   │   └── CheckInService.php        ← Atomic check-in with row lock
│   │   │   ├── Notification/
│   │   │   │   └── NotificationService.php   ← Email dispatch, extensible for SMS
│   │   │   ├── Admin/
│   │   │   │   └── AuditService.php          ← Audit log recording
│   │   │   └── Reporting/
│   │   │       └── ReportService.php         ← Sales, payment, attendee reports
│   │   │
│   │   ├── Jobs/
│   │   │   ├── ProcessWebhookEvent.php
│   │   │   ├── ExpireReservations.php
│   │   │   ├── ReconcilePayments.php
│   │   │   ├── SendTicketEmail.php
│   │   │   └── SendPaymentReceiptEmail.php
│   │   │
│   │   ├── Events/                           ← Laravel domain events
│   │   │   ├── PaymentVerified.php
│   │   │   ├── TicketsIssued.php
│   │   │   ├── OrderPaid.php
│   │   │   └── CheckInRecorded.php
│   │   │
│   │   ├── Listeners/
│   │   │   ├── IssueTicketsOnPaymentVerified.php
│   │   │   ├── SendTicketEmailOnIssued.php
│   │   │   └── LogCheckIn.php
│   │   │
│   │   ├── Exceptions/
│   │   │   ├── InsufficientInventoryException.php
│   │   │   ├── PaymentVerificationException.php
│   │   │   ├── InvalidWebhookSignatureException.php
│   │   │   ├── DuplicateCheckInException.php
│   │   │   └── ReservationExpiredException.php
│   │   │
│   │   └── Console/
│   │       └── Commands/
│   │           ├── CreateSuperAdmin.php       ← Interactive, validates password, forces MFA
│   │           └── ReconcilePayments.php
│   │
│   ├── config/
│   │   ├── paystack.php                       ← Paystack config (reads from env)
│   │   └── ticketing.php                      ← App-specific config (reservation TTL, etc.)
│   │
│   ├── database/
│   │   ├── migrations/                        ← All 17 migration files
│   │   ├── factories/                         ← One factory per model
│   │   └── seeders/
│   │       ├── DatabaseSeeder.php
│   │       ├── RolePermissionSeeder.php        ← Seeds roles + permissions
│   │       └── DemoEventSeeder.php             ← Dev/test only
│   │
│   ├── routes/
│   │   ├── api.php                            ← All v1 route groups
│   │   └── console.php                        ← Scheduled commands
│   │
│   ├── tests/
│   │   ├── Unit/
│   │   │   ├── Enums/
│   │   │   ├── Services/
│   │   │   └── Models/
│   │   ├── Feature/
│   │   │   ├── Auth/
│   │   │   │   ├── LoginTest.php
│   │   │   │   ├── MfaTest.php
│   │   │   │   ├── LogoutTest.php
│   │   │   │   └── SessionTest.php
│   │   │   ├── Admin/
│   │   │   │   ├── EventManagementTest.php
│   │   │   │   ├── TicketTypeManagementTest.php
│   │   │   │   ├── AdminManagementTest.php
│   │   │   │   └── RolePermissionTest.php
│   │   │   ├── Public/
│   │   │   │   ├── EventListingTest.php
│   │   │   │   └── EventDetailTest.php
│   │   │   ├── Checkout/
│   │   │   │   ├── CheckoutValidationTest.php
│   │   │   │   ├── PriceTamperingTest.php
│   │   │   │   ├── InventoryReservationTest.php
│   │   │   │   ├── InventoryConcurrencyTest.php
│   │   │   │   └── ReservationExpirationTest.php
│   │   │   ├── Payment/
│   │   │   │   ├── PaystackChargeTest.php
│   │   │   │   ├── PaymentStateTest.php
│   │   │   │   └── PaymentVerificationTest.php
│   │   │   ├── Webhook/
│   │   │   │   ├── SignatureVerificationTest.php
│   │   │   │   ├── DeduplicationTest.php
│   │   │   │   ├── ReplayProtectionTest.php
│   │   │   │   └── OutOfOrderTest.php
│   │   │   ├── Ticketing/
│   │   │   │   ├── TicketIssuanceTest.php
│   │   │   │   ├── DuplicateIssuanceTest.php
│   │   │   │   ├── TicketRetrievalTest.php
│   │   │   │   └── QrTokenTest.php
│   │   │   ├── CheckIn/
│   │   │   │   ├── ValidCheckInTest.php
│   │   │   │   ├── InvalidTokenTest.php
│   │   │   │   ├── DuplicateScanTest.php
│   │   │   │   ├── ConcurrentScanTest.php
│   │   │   │   └── WrongEventTest.php
│   │   │   ├── Authorization/
│   │   │   │   ├── IdorTest.php
│   │   │   │   └── PrivilegeEscalationTest.php
│   │   │   └── RateLimit/
│   │   │       └── RateLimitTest.php
│   │   └── Integration/
│   │       └── PaystackSandboxTest.php        ← Uses test keys against Paystack sandbox
│   │
│   ├── composer.json
│   ├── phpunit.xml
│   ├── phpstan.neon
│   └── .env.example
│
├── frontend/                                   ← Next.js 15 application
│   ├── app/
│   │   ├── layout.tsx                          ← Root layout
│   │   ├── (public)/
│   │   │   ├── page.tsx                        ← Homepage / event listing
│   │   │   ├── events/
│   │   │   │   └── [slug]/
│   │   │   │       └── page.tsx                ← Event detail + ticket selection
│   │   │   ├── checkout/
│   │   │   │   └── [eventSlug]/
│   │   │   │       └── page.tsx                ← Checkout form
│   │   │   ├── payment/
│   │   │   │   └── [reference]/
│   │   │   │       └── page.tsx                ← Payment status (polling)
│   │   │   └── tickets/
│   │   │       └── [accessToken]/
│   │   │           └── page.tsx                ← Secure ticket retrieval
│   │   ├── (auth)/
│   │   │   ├── login/
│   │   │   │   └── page.tsx
│   │   │   └── mfa/
│   │   │       └── page.tsx
│   │   ├── admin/
│   │   │   ├── layout.tsx                      ← Admin layout with sidebar, auth guard
│   │   │   ├── dashboard/
│   │   │   │   └── page.tsx
│   │   │   ├── events/
│   │   │   │   ├── page.tsx                    ← Event list
│   │   │   │   ├── new/page.tsx
│   │   │   │   └── [id]/
│   │   │   │       ├── page.tsx                ← Event detail/edit
│   │   │   │       └── ticket-types/page.tsx
│   │   │   ├── orders/
│   │   │   │   ├── page.tsx
│   │   │   │   └── [id]/page.tsx
│   │   │   ├── payments/
│   │   │   │   ├── page.tsx
│   │   │   │   └── reconciliation/page.tsx
│   │   │   ├── attendees/
│   │   │   │   └── page.tsx
│   │   │   ├── reports/
│   │   │   │   └── page.tsx
│   │   │   ├── audit-logs/
│   │   │   │   └── page.tsx
│   │   │   ├── users/
│   │   │   │   ├── page.tsx
│   │   │   │   └── [id]/page.tsx
│   │   │   └── settings/
│   │   │       └── page.tsx
│   │   └── staff/
│   │       ├── layout.tsx                      ← Staff layout with auth guard
│   │       └── scan/
│   │           └── [eventId]/
│   │               └── page.tsx                ← QR scanner + check-in
│   │
│   ├── components/
│   │   ├── ui/                                 ← shadcn/ui components
│   │   ├── layout/
│   │   │   ├── Header.tsx
│   │   │   ├── Footer.tsx
│   │   │   ├── AdminSidebar.tsx
│   │   │   ├── AdminLayout.tsx
│   │   │   └── StaffLayout.tsx
│   │   ├── events/
│   │   │   ├── EventCard.tsx
│   │   │   ├── EventGrid.tsx
│   │   │   ├── EventDetail.tsx
│   │   │   └── EventSearch.tsx
│   │   ├── checkout/
│   │   │   ├── TicketSelector.tsx
│   │   │   ├── OrderSummary.tsx
│   │   │   ├── CustomerInfoForm.tsx
│   │   │   ├── MobileMoneyProviderSelect.tsx
│   │   │   └── CheckoutStepper.tsx
│   │   ├── payment/
│   │   │   ├── PaymentStatusDisplay.tsx
│   │   │   ├── MobileMoneyPrompt.tsx
│   │   │   └── PaymentStatusPoller.tsx
│   │   ├── tickets/
│   │   │   ├── TicketCard.tsx
│   │   │   ├── QrCodeDisplay.tsx
│   │   │   └── TicketDownload.tsx
│   │   ├── admin/
│   │   │   ├── DataTable.tsx
│   │   │   ├── StatsCard.tsx
│   │   │   ├── SalesTrendChart.tsx
│   │   │   ├── EventForm.tsx
│   │   │   ├── TicketTypeForm.tsx
│   │   │   └── ConfirmDialog.tsx
│   │   └── scan/
│   │       ├── QrScanner.tsx
│   │       ├── CheckInResult.tsx
│   │       └── CheckInStats.tsx
│   │
│   ├── lib/
│   │   ├── api-client.ts                       ← Typed fetch wrapper with CSRF
│   │   ├── auth.ts                             ← Session helpers, auth state
│   │   ├── utils.ts                            ← formatMoney(), formatDate(), cn()
│   │   └── constants.ts                        ← API base URL, payment providers
│   │
│   ├── hooks/
│   │   ├── useAuth.ts
│   │   ├── usePaymentStatus.ts                 ← Bounded polling with backoff
│   │   ├── useCheckout.ts
│   │   └── useDebounce.ts
│   │
│   ├── types/
│   │   ├── event.ts
│   │   ├── order.ts
│   │   ├── payment.ts
│   │   ├── ticket.ts
│   │   ├── admin.ts
│   │   └── api.ts                              ← API response envelopes
│   │
│   ├── __tests__/
│   │   ├── components/
│   │   └── hooks/
│   │
│   ├── middleware.ts                           ← Next.js middleware for admin route protection
│   ├── next.config.ts
│   ├── tailwind.config.ts
│   ├── tsconfig.json
│   └── package.json
│
├── infra/
│   ├── docker/
│   │   ├── backend/
│   │   │   ├── Dockerfile                      ← PHP 8.3-FPM, pinned, multi-stage
│   │   │   └── php.ini                         ← Production PHP settings
│   │   ├── frontend/
│   │   │   └── Dockerfile                      ← Node 22 LTS, pinned, multi-stage
│   │   └── nginx/
│   │       ├── Dockerfile                      ← Nginx stable, pinned
│   │       ├── nginx.conf
│   │       └── default.conf                    ← Reverse proxy + security headers
│   └── scripts/
│       ├── backup.sh                           ← pg_dump + encryption + offsite upload
│       ├── restore.sh                          ← Download + decrypt + pg_restore
│       └── wait-for-it.sh                      ← Container startup dependency
│
├── docker-compose.yml                          ← Development environment
├── docker-compose.prod.yml                     ← Production overrides
├── .env.example                                ← Root env template
├── .gitignore
└── README.md
```

---

## 5. Database Schema Overview

### Tables (17 total)

| Table | Primary Purpose | Key Constraints |
|---|---|---|
| `admins` | Admin/staff accounts | UNIQUE email, Argon2id password |
| `roles` | RBAC roles | UNIQUE name |
| `permissions` | Granular permissions | UNIQUE name |
| `admin_roles` | Admin ↔ Role pivot | Composite PK |
| `role_permissions` | Role ↔ Permission pivot | Composite PK |
| `events` | Event catalog | UNIQUE slug, CHECK ends_at > starts_at |
| `ticket_types` | Ticket categories per event | FK events, CHECK price >= 0, CHECK quantity >= 0 |
| `orders` | Customer orders | UNIQUE order_number, FK events |
| `order_items` | Line items with price snapshots | FK orders, FK ticket_types |
| `inventory_reservations` | Temporary inventory holds | FK orders, FK ticket_types, expiry indexed |
| `payments` | Payment attempts | UNIQUE reference, FK orders |
| `webhook_events` | Paystack webhook dedup | UNIQUE(provider, event_identifier) |
| `tickets` | Issued tickets | UNIQUE ticket_number, UNIQUE qr_token_hash |
| `check_ins` | Gate admission records | UNIQUE(ticket_id), FK tickets, FK admins |
| `audit_logs` | Administrative audit trail | Append-only by design |
| `notification_logs` | Email delivery tracking | FK orders, FK tickets |
| `system_settings` | Non-secret operational config | No secrets stored |

### All Primary Keys: UUID
### All Timestamps: TIMESTAMPTZ (UTC)
### All Money Columns: BIGINT (pesewas)

---

## 6. Paystack Ghana Mobile Money Integration

### Confirmed from Official Paystack Documentation (November 2026)

#### Flow Summary

```
Customer → Frontend → Laravel API → Paystack Charge API
                                         ↓
                              Response: pay_offline
                              display_text: "Authorize on phone"
                                         ↓
                              Customer receives STK/USSD prompt
                              Customer enters PIN on their phone (~180s)
                                         ↓
                              Paystack → Webhook → Laravel
                              Laravel verifies signature
                              Laravel verifies transaction (GET /verify)
                              Laravel matches ref + amount + currency
                              Laravel fulfills order atomically
```

#### API Endpoints

| Purpose | Method | URL |
|---|---|---|
| Initiate charge | `POST` | `https://api.paystack.co/charge` |
| Submit OTP (if required) | `POST` | `https://api.paystack.co/charge/submit_otp` |
| Verify transaction | `GET` | `https://api.paystack.co/transaction/verify/{reference}` |

#### Ghana Mobile Money Provider Codes

| Network | Code | Notes |
|---|---|---|
| MTN Mobile Money | `mtn` | Largest network |
| AirtelTigo / AT Money | `atl` | |
| Telecel Cash | `vod` | Formerly Vodafone Cash |

#### Charge Request Payload

```json
{
  "email": "customer@email.com",
  "amount": 5000,
  "currency": "GHS",
  "reference": "ORD-xxxx-PAY-1",
  "mobile_money": {
    "phone": "0551234567",
    "provider": "mtn"
  }
}
```

- `amount`: Integer pesewas (GHS 50.00 = 5000)
- `currency`: Always `"GHS"`
- `reference`: Our unique reference, tied to the payment record
- `mobile_money.provider`: `mtn` | `atl` | `vod`

#### Expected Response (MoMo)

```json
{
  "status": true,
  "message": "Charge attempted",
  "data": {
    "reference": "ORD-xxxx-PAY-1",
    "status": "pay_offline",
    "display_text": "Please approve the transaction on your phone"
  }
}
```

#### Webhook Signature Verification

- **Header**: `x-paystack-signature`
- **Algorithm**: HMAC-SHA512
- **Key**: Paystack Secret Key
- **Input**: Raw request body (before JSON parsing)
- **Comparison**: Constant-time (`hash_equals()` in PHP)
- **Modern alternative**: `Paystack-Signature` header with `t=<timestamp>,v1=<keyid>:<signature>` — support both

#### Verification Response (Key Fields to Match)

```json
{
  "status": true,
  "data": {
    "status": "success",
    "reference": "ORD-xxxx-PAY-1",
    "amount": 5000,
    "currency": "GHS",
    "channel": "mobile_money",
    "paid_at": "2026-11-12T12:30:56.000Z",
    "fees": 98
  }
}
```

Must verify ALL of:
- `data.status === "success"`
- `data.reference` matches our payment record
- `data.amount` matches our expected amount
- `data.currency === "GHS"` matches our expected currency

Record `data.fees` for organizer reporting (not shown to customer).

#### Internal Payment State Mapping

| Paystack `data.status` | Internal `PaymentStatus` | Action |
|---|---|---|
| (charge initiated, `pay_offline`) | `pending` | Wait for webhook/poll |
| `success` | `success` | Verify then fulfill |
| `failed` | `failed` | Release reservation |
| `abandoned` | `abandoned` | Release reservation |
| `reversed` | `reversed` | Update order state |
| `pending` / `processing` | `pending` | Continue waiting |

#### What We Will NOT Do
- ❌ Collect Mobile Money PIN in any UI
- ❌ Persist OTP values
- ❌ Build custom OTP/PIN entry forms
- ❌ Assume identical flows across networks
- ❌ Put secret key in frontend code or logs
- ❌ Trust frontend redirect as proof of payment
- ❌ Retry charges when outcome is uncertain
- ❌ Show Paystack fees to the customer

---

## 7. Security-Critical Components

### Tier 1 — Critical (Payment & Ticket Integrity)

| Component | Security Controls |
|---|---|
| **Webhook signature verification** | Raw body HMAC-SHA512, constant-time comparison, reject before any processing |
| **Payment verification** | Independent `GET /transaction/verify`, match ref + amount + currency, never trust frontend |
| **Inventory locking** | `SELECT ... FOR UPDATE`, atomic reservation, DB constraints as final guard |
| **Ticket issuance** | Exactly-once via unique constraints, only after verified payment, inside DB transaction |
| **QR token security** | `random_bytes(32)`, SHA-256 hash stored, raw token never stored/logged |
| **Check-in atomicity** | Row lock → verify event/status/prior check-in → create check_in + update ticket → commit, UNIQUE(ticket_id) |

### Tier 2 — High (Access Control)

| Component | Security Controls |
|---|---|
| **Admin authentication** | Argon2id, MFA (TOTP), session management, reauthentication |
| **RBAC authorization** | Policies on every endpoint, deny-by-default, IDOR tests |
| **Session security** | Secure/HttpOnly/SameSite cookies, CSRF, session regeneration, idle+absolute expiry |
| **Rate limiting** | Per-endpoint limits on login, checkout, retrieval, webhook |
| **CORS** | Strict allowlist: `ticket.clevawave.com` only |

### Tier 3 — Medium (Data Protection)

| Component | Security Controls |
|---|---|
| **File uploads** | MIME validation, re-encoding, random filenames, non-executable storage |
| **API responses** | Resources only, no model serialization, provider payloads sanitized |
| **Audit logging** | No secrets in metadata, append-only design, tamper-resistant |
| **Error handling** | No stack traces in production, correlation IDs, generic error messages |

### Critical Security Invariants

These must NEVER be violated:

1. **No ticket without verified payment** — tickets created only inside a DB transaction that has confirmed `payment.status = 'success'` via direct Paystack verification
2. **No overselling** — `quantity_sold + active_reserved <= quantity_total` enforced by row lock + check, with DB constraints as final defense
3. **No double admission** — `UNIQUE(ticket_id)` on `check_ins` + row lock on ticket
4. **No webhook forgery** — invalid HMAC = immediate 401, no processing
5. **No secret leakage** — Paystack secret key never in frontend/logs/errors; QR raw tokens never stored/logged
6. **No fee exposure** — Paystack processing fees never shown to or charged to the customer

---

## 8. Implementation Phases

### Phase 0 — Repository & Architecture Baseline

**Goal**: Working local development environment with all tooling configured.

**Deliverables**:
- [ ] Git repository initialized with `.gitignore`
- [ ] Laravel 11 project in `backend/`
- [ ] Next.js 15 project in `frontend/` with TypeScript strict mode
- [ ] Docker Compose: PostgreSQL 16, Redis 7, PHP-FPM 8.3, Nginx, Next.js dev server
- [ ] Health endpoints: `GET /api/v1/health` (Laravel), health check (Next.js)
- [ ] `.env.example` with all variables documented
- [ ] `README.md` with setup instructions
- [ ] Initial Tailwind + shadcn/ui setup
- [ ] PHPStan + Pint configured for backend
- [ ] ESLint + Prettier configured for frontend
- [ ] CI config skeleton

**Tests**:
- [ ] `docker-compose up` starts all services
- [ ] Health endpoints respond
- [ ] Frontend builds without errors
- [ ] Backend artisan commands work

**Files**: ~25 files

---

### Phase 1 — Foundation & Database

**Goal**: Complete database schema with all models, factories, and constraints.

**Deliverables**:
- [ ] 17 migration files with all constraints per DATABASE_DESIGN.md
- [ ] UUID primary keys on all tables
- [ ] BIGINT money columns with CHECK >= 0
- [ ] PHP Enums for all status fields (7 enums)
- [ ] Foreign keys with RESTRICT on financial/ticket records
- [ ] Check constraints: `ends_at > starts_at`, positive quantities, non-negative amounts
- [ ] All unique constraints per §4 of DATABASE_DESIGN.md
- [ ] Indexes: orders by event/status, payments by reference/status, tickets by event, reservations by expiry
- [ ] 15 Eloquent models with relationships, casts, fillable/guarded
- [ ] Model factories for all models
- [ ] RolePermissionSeeder with initial roles and permissions
- [ ] DemoEventSeeder for development

**Roles seeded**:
- Super Admin — full access
- Event Manager — manage assigned events and ticket types
- Finance Manager — view payments, reconciliation, financial reports
- Gate Staff — check-in for assigned events

**Permissions seeded** (examples):
- `events.create`, `events.update`, `events.publish`, `events.cancel`, `events.archive`
- `ticket_types.create`, `ticket_types.update`
- `orders.view`, `orders.export`
- `payments.view`, `payments.reconcile`
- `reports.view`, `reports.export`
- `admins.create`, `admins.disable`
- `roles.manage`
- `audit_logs.view`
- `check_in.scan`
- `settings.manage`

**Tests**:
- [ ] Migrate from empty database
- [ ] Migrate rollback (where safe)
- [ ] Model relationship tests
- [ ] Constraint violation tests (duplicate slug, negative amount, etc.)
- [ ] Factory generation tests

**Files**: ~45 files

---

### Phase 2 — Admin Identity & Permissions

**Goal**: Secure admin authentication with MFA, RBAC, and audit logging.

**Deliverables**:
- [ ] Sanctum stateful cookie-based auth configured for `ticket.clevawave.com`
- [ ] Login/Logout with rate limiting and generic error messages
- [ ] MFA (TOTP) setup and verification; required for Super Admin; enforced via middleware
- [ ] Argon2id password hashing
- [ ] `php artisan admin:create-super` command — interactive, password strength validation, forces MFA setup on first login
- [ ] Admin CRUD API (Super Admin only): create, view, disable, re-enable
- [ ] Role/permission assignment API
- [ ] Policies and Gates for all protected resources
- [ ] AuditService — records actor, action, target, IP, user-agent, metadata (no secrets)
- [ ] Session management: regeneration after login, idle timeout (30min), absolute expiry (8hr), revocation on logout/disable
- [ ] Reauthentication middleware for high-risk actions
- [ ] Frontend: Login page, MFA verification page, admin layout with sidebar

**Session/Cookie Configuration for `ticket.clevawave.com`**:
```php
SANCTUM_STATEFUL_DOMAINS=ticket.clevawave.com
SESSION_DOMAIN=.clevawave.com
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
```

**CORS Configuration**:
```php
'allowed_origins' => ['https://ticket.clevawave.com'],
'supports_credentials' => true,
```

**Tests**:
- [ ] Login success/failure
- [ ] MFA enforcement (login without MFA → forced setup)
- [ ] Session expiry (idle + absolute)
- [ ] Session revocation on logout/disable
- [ ] Role-based access control enforcement
- [ ] Permission-based access control enforcement
- [ ] Privilege escalation attempts blocked
- [ ] IDOR tests (admin A cannot manage admin B's resources without permission)
- [ ] Rate limiting on login endpoint
- [ ] Audit log entries created for auth events

**Files**: ~35 files

---

### Phase 3 — Events & Ticket Catalog

**Goal**: Full event and ticket type management for admins; public event listing.

**Deliverables**:
- [ ] Event CRUD API: create, read, update, publish, unpublish, cancel, archive
- [ ] Event slug auto-generation (unique, URL-safe)
- [ ] Event status transitions enforced (draft → published → unpublished/cancelled → archived)
- [ ] Sale windows: event-level and ticket-type-level, validated
- [ ] Ticket type CRUD: create, read, update, activate, deactivate, archive
- [ ] Quantity reduction guard: cannot reduce below `quantity_sold + active_reserved`
- [ ] Banner upload: file type allowlist, MIME check, re-encode, random filename, safe storage
- [ ] Public event listing API: `GET /api/v1/events` — published only, paginated, filterable
- [ ] Public event detail API: `GET /api/v1/events/{slug}` — with available ticket types and computed availability
- [ ] Admin event management screens (list, create, edit, manage ticket types)
- [ ] Public homepage: event grid with cards
- [ ] Public event detail page: title, description, banner, venue, times (Africa/Accra), ticket types with prices and availability

**Tests**:
- [ ] Published-only public visibility
- [ ] Draft/cancelled/archived events not visible to public
- [ ] Invalid sale window rejected
- [ ] Quantity/price validation
- [ ] Quantity reduction below sold+reserved rejected
- [ ] Permission tests (Event Manager can manage assigned events only)
- [ ] File upload security (invalid MIME rejected, size limits)
- [ ] Event status transition tests
- [ ] Historical data preserved on event changes

**Files**: ~40 files

---

### Phase 4 — Anonymous Checkout & Inventory

**Goal**: Guest checkout with atomic inventory reservation and expiration.

**Deliverables**:
- [ ] Checkout API: `POST /api/v1/checkout`
  - Validates event is published and purchasable
  - Validates ticket types are active and within sale window
  - Validates quantity limits (min/max per order, available stock)
  - Server-side price calculation (no client totals trusted)
  - Creates order with unique non-sequential `order_number`
  - Creates order items with snapshot of ticket name, price, currency
  - Atomically reserves inventory (`SELECT ... FOR UPDATE` on ticket_type)
  - Returns order summary with payment instructions
- [ ] Phone number validation: Ghanaian format, international normalization, no network-from-prefix assumption
- [ ] Reservation expiration scheduled job: runs every minute, releases expired reservations, adjusts inventory counts
- [ ] Checkout UI: ticket selector → order summary → customer info form → confirm
- [ ] Order summary displays backend-calculated totals only
- [ ] Reservation countdown timer (display only; backend enforces expiry)

**Inventory Reservation Flow**:
```
BEGIN TRANSACTION
  SELECT * FROM ticket_types WHERE id = ? FOR UPDATE
  available = quantity_total - quantity_sold - active_reserved_count
  IF available < requested_quantity → ROLLBACK, return error
  INSERT order (status: pending_payment)
  INSERT order_items (with price snapshot)
  INSERT inventory_reservation (status: active, expires_at: now + 15min)
  UPDATE ticket_types SET quantity_reserved = quantity_reserved + requested_quantity
COMMIT
```

**Tests**:
- [ ] Guest checkout without account
- [ ] Client price tampering rejected (modified totals ignored; server recalculates)
- [ ] Quantity below minimum rejected
- [ ] Quantity above maximum rejected
- [ ] Quantity exceeding available stock rejected
- [ ] Concurrent last-ticket purchase (two checkouts for last ticket — only one succeeds)
- [ ] Reservation expiration releases inventory
- [ ] Expired reservation restores available count
- [ ] No overselling under concurrent load
- [ ] Invalid phone number rejected
- [ ] Unpublished event checkout rejected
- [ ] Expired sale window checkout rejected

**Files**: ~30 files

---

### Phase 5 — Paystack Mobile Money Integration

**Goal**: Server-side payment initiation and state tracking.

**Deliverables**:
- [ ] `PaystackAdapter` service: HTTP client for Paystack API with timeout, error handling
- [ ] Charge initiation: `POST /charge` with `mobile_money` object
- [ ] Provider selection UI: customer chooses MTN, AirtelTigo, or Telecel
- [ ] Response handling: `pay_offline` → store payment as `pending`, display `display_text`
- [ ] Payment record creation: reference, amount, currency, provider, status, sanitized response
- [ ] Payment status API: `GET /api/v1/payments/{reference}/status` — rate-limited
- [ ] Transaction verification service: `GET /transaction/verify/{reference}` — backend only
- [ ] Payment state machine: allowed transitions documented and enforced
- [ ] Handle Paystack initiation failure: release reservation safely
- [ ] Payment status UI: pending → "authorize on phone" → success/failure/expired
- [ ] Bounded polling with exponential backoff (max 180s, then show "check later" with retrieval link)

**Tests**:
- [ ] Paystack test-mode charge initiation
- [ ] Pending → success flow
- [ ] Pending → failure flow
- [ ] Pending → expired flow (timeout)
- [ ] Incorrect amount in verification → rejected
- [ ] Incorrect currency → rejected
- [ ] Incorrect reference → rejected
- [ ] Network timeout handling (Paystack unreachable)
- [ ] No PIN collection forms exist in codebase
- [ ] No OTP persistence
- [ ] Payment record sanitization (no secrets stored)

**Files**: ~25 files

---

### Phase 6 — Webhooks & Reconciliation

**Goal**: Bulletproof webhook processing with deduplication, verification, and reconciliation.

**Deliverables**:
- [ ] Webhook endpoint: `POST /api/v1/webhooks/paystack` — excluded from CSRF
- [ ] `CaptureRawBody` middleware: reads raw request body before JSON parsing
- [ ] Signature verification: HMAC-SHA512 with `hash_equals()`, both header formats supported
- [ ] Webhook event persistence: deduplicate by `(provider, event_identifier)`, safe upsert
- [ ] Queue dispatch: `ProcessWebhookEvent` job dispatched after quick acknowledgement (200)
- [ ] Job processing: verify transaction with Paystack → match ref/amount/currency → fulfill
- [ ] Idempotent fulfillment: lock rows → check if fulfilled → transition → issue tickets → commit
- [ ] Reconciliation service: scheduled every 5 minutes
  - Finds pending payments older than 10 minutes
  - Verifies each with Paystack
  - Fulfills confirmed payments
  - Flags mismatches for manual review
  - Handles late success after reservation expiry (flag, don't auto-fulfill if no inventory)
- [ ] Operational alerts: invalid signatures, repeated failures, stuck payments, mismatches
- [ ] Dead-letter handling: failed webhook jobs after max retries → alert

**Webhook Processing Flow**:
```
1. Receive POST /api/v1/webhooks/paystack
2. Read raw body (CaptureRawBody middleware)
3. Verify HMAC-SHA512 signature → 401 if invalid
4. Parse JSON, extract event type and identifier
5. INSERT webhook_event (ON CONFLICT → mark duplicate, return 200)
6. Dispatch ProcessWebhookEvent job
7. Return 200 immediately
8. [Job] Verify transaction: GET /transaction/verify/{reference}
9. [Job] Match reference, amount, currency to payment record
10. [Job] BEGIN TRANSACTION
     Lock payment, order, reservation, ticket_type rows (consistent order)
     If already fulfilled → return (idempotent)
     Transition payment status → success
     Transition order status → paid
     Convert reservation → converted
     Adjust ticket_type quantity_sold
     Issue tickets (Phase 7)
    COMMIT
11. Dispatch notifications after commit
```

**Tests**:
- [ ] Valid signature accepted
- [ ] Invalid signature → 401
- [ ] Modified body → signature mismatch → 401
- [ ] Duplicate webhook event → idempotent (no double processing)
- [ ] Replayed webhook event → harmless
- [ ] Out-of-order events handled correctly
- [ ] Provider timeout during verification → retry with backoff
- [ ] Reconciliation finds and fulfills missed webhook payment
- [ ] Reconciliation flags amount mismatch
- [ ] Late payment after expired reservation → flagged for review, not auto-fulfilled
- [ ] Concurrent webhook delivery → only one processes (idempotent)

**Files**: ~20 files

---

### Phase 7 — Ticket Issuance & Retrieval

**Goal**: Secure ticket generation, QR codes, email delivery, and retrieval.

**Deliverables**:
- [ ] `TicketIssuanceService`: creates exactly `quantity` tickets per order item
- [ ] `QrTokenService`: generates 32-byte crypto-random token, returns raw + SHA-256 hash
- [ ] Ticket number generation: unique, human-readable, non-sequential (e.g., `TKT-XXXX-XXXX`)
- [ ] QR code generation: `bacon/bacon-qr-code`, payload is opaque token only
- [ ] Confirmation page: event details, ticket details, QR codes rendered
- [ ] Ticket email: queued via `SendTicketEmail` job, includes QR image, event info, ticket number
- [ ] Payment receipt email: queued via `SendPaymentReceiptEmail` job
- [ ] Access token for retrieval: 64-char hex token generated per order, stored hashed, included in confirmation URL
- [ ] Retrieval endpoint: `GET /api/v1/tickets/{accessToken}` — returns order + tickets
- [ ] Rate limiting on retrieval endpoint
- [ ] Re-send email endpoint (does not create new tickets)
- [ ] Frontend ticket retrieval page: displays tickets with QR codes

**QR Token Flow**:
```
raw_token = random_bytes(32)  → base64url encode → goes into QR code and email
token_hash = hash('sha256', raw_token) → stored in tickets.qr_token_hash
raw_token is NEVER stored in the database or logged
```

**Tests**:
- [ ] Exact ticket count per order (3 tickets ordered → 3 tickets created)
- [ ] Duplicate payment processing → no additional tickets (idempotent)
- [ ] Token has sufficient entropy (32 bytes = 256 bits)
- [ ] Token hash lookup works
- [ ] Raw token not found in any database column
- [ ] Retrieval with valid access token succeeds
- [ ] Retrieval with invalid/guessed token fails
- [ ] Cannot retrieve another customer's tickets
- [ ] Rate limiting enforced on retrieval
- [ ] Notification failure does not roll back ticket issuance
- [ ] Re-send email does not create duplicate tickets

**Files**: ~25 files

---

### Phase 8 — Gate Validation (Check-In)

**Goal**: Staff scanning interface with atomic check-in and concurrency safety.

**Deliverables**:
- [ ] Staff authentication: Sanctum session with Gate Staff role
- [ ] Event assignment: staff assigned to specific events via admin
- [ ] QR scanning UI: camera-based scanner using `html5-qrcode`
- [ ] Check-in API: `POST /api/v1/staff/check-in` — receives raw QR token
- [ ] Atomic check-in service:
  1. Hash received token
  2. Find ticket by `qr_token_hash`
  3. `SELECT ... FOR UPDATE` on ticket row
  4. Verify: ticket exists, event matches staff assignment, status is `active`, no prior check-in
  5. Insert `check_ins` record
  6. Update ticket status to `checked_in`
  7. Commit
- [ ] Response states: `valid` ✓, `already_checked_in` ✗, `wrong_event` ✗, `cancelled` ✗, `refunded` ✗, `invalid` ✗
- [ ] Check-in dashboard: real-time count of checked-in vs. total for assigned event
- [ ] Audio/visual feedback on scan result

**Tests**:
- [ ] Valid ticket check-in succeeds
- [ ] Invalid/unknown token rejected
- [ ] Wrong event rejected (staff scanning ticket for different event)
- [ ] Cancelled ticket rejected
- [ ] Refunded ticket rejected
- [ ] Already-checked-in ticket → clear "already used" message
- [ ] Two scanners race on same ticket → only one succeeds (concurrent scan)
- [ ] Unauthorized staff (not assigned to event) rejected
- [ ] Unauthenticated request rejected
- [ ] Check-in count updates correctly

**Files**: ~20 files

---

### Phase 9 — Admin Reports & Operational Tools

**Goal**: Comprehensive admin dashboard with reports and operational visibility.

**Deliverables**:
- [ ] Dashboard API: permission-scoped metrics
  - Events active/upcoming count
  - Tickets sold / remaining (per event and total)
  - Orders by status (pending, paid, expired, cancelled)
  - Payments by status (only `success` counted as revenue)
  - Gross paid amount (excludes pending — not labeled as revenue)
  - Refunds/reversals separately
  - Recent activity feed
  - Sales trend (last 30 days)
- [ ] Sales reports: by event, by ticket type, by date range, filterable
- [ ] Payment reports: by status, reconciliation view, mismatch alerts
- [ ] Attendee list: per event, with check-in status, searchable
- [ ] CSV export: permission-gated, data-minimized (email/phone partially masked)
- [ ] Audit log viewer: filterable by admin, action, target, date
- [ ] Queue/system health: failed jobs count, pending webhooks, stuck payments
- [ ] Admin dashboard frontend: charts, data tables, filters, export buttons

**Tests**:
- [ ] Report calculations match actual data
- [ ] Permission scoping (Event Manager sees only assigned events)
- [ ] Finance Manager cannot alter payment records
- [ ] Export respects data minimization
- [ ] Large result pagination works correctly
- [ ] Audit logs cannot be edited/deleted via API

**Files**: ~25 files

---

### Phase 10 — Hardening & Release

**Goal**: Production-ready security, deployment, monitoring, and recovery.

**Deliverables**:
- [ ] Production Dockerfiles: pinned versions, multi-stage builds, non-root user
- [ ] Nginx configuration: TLS termination, security headers
  - `Strict-Transport-Security: max-age=31536000; includeSubDomains`
  - `Content-Security-Policy` (restrictive)
  - `X-Content-Type-Options: nosniff`
  - `X-Frame-Options: DENY`
  - `Referrer-Policy: strict-origin-when-cross-origin`
  - Rate limiting at Nginx level
- [ ] Secrets management: all secrets via environment variables, never in source
- [ ] Backup automation: `pg_dump` + WAL archiving, encrypted, offsite, restore tested
- [ ] Monitoring: Sentry for exceptions (Laravel + Next.js), structured JSON logs
- [ ] Health checks: `/health` endpoint with DB/Redis/queue status
- [ ] CI/CD pipeline:
  - Lint (ESLint, Pint)
  - Type check (TypeScript, PHPStan)
  - Test suites
  - Build verification
  - Dependency vulnerability scan
  - Secret scan
  - Container image scan
- [ ] Deployment runbook: zero-downtime deploy, migration strategy, rollback procedure
- [ ] Backup/restore runbook: RPO 15min, RTO 4hr targets
- [ ] Security assessment: OWASP ASVS L2 checklist verification
- [ ] Incident response procedure document

**Tests**:
- [ ] Full regression suite passes
- [ ] SAST/dependency/container scans clean
- [ ] Load test: concurrent checkout simulation
- [ ] Backup restore test successful
- [ ] Staging end-to-end test passes
- [ ] Production smoke test with Paystack test mode

**Files**: ~20 files

---

## 9. Environment Variables

```env
# ─── Application ───────────────────────────────────────
APP_NAME="ClevaWave Ticketing"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=https://ticket.clevawave.com/api
APP_TIMEZONE=UTC

# ─── Frontend ──────────────────────────────────────────
FRONTEND_URL=https://ticket.clevawave.com
NEXT_PUBLIC_API_URL=https://ticket.clevawave.com/api/v1

# ─── PostgreSQL ────────────────────────────────────────
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=ticketing
DB_USERNAME=ticketing_app
DB_PASSWORD=
DB_MIGRATION_USERNAME=ticketing_migrations
DB_MIGRATION_PASSWORD=

# ─── Redis ─────────────────────────────────────────────
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=

# ─── Paystack ──────────────────────────────────────────
PAYSTACK_PUBLIC_KEY=pk_test_...
PAYSTACK_SECRET_KEY=sk_test_...
PAYSTACK_WEBHOOK_SECRET=
PAYSTACK_BASE_URL=https://api.paystack.co

# ─── Session / Sanctum ────────────────────────────────
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_DOMAIN=.clevawave.com
SANCTUM_STATEFUL_DOMAINS=ticket.clevawave.com
SESSION_SECURE_COOKIE=true

# ─── CORS ──────────────────────────────────────────────
CORS_ALLOWED_ORIGINS=https://ticket.clevawave.com

# ─── Mail ──────────────────────────────────────────────
MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=tickets@clevawave.com
MAIL_FROM_NAME="ClevaWave Ticketing"

# ─── Queue ─────────────────────────────────────────────
QUEUE_CONNECTION=redis

# ─── Storage ───────────────────────────────────────────
FILESYSTEM_DISK=local

# ─── Monitoring ────────────────────────────────────────
SENTRY_LARAVEL_DSN=
SENTRY_NEXTJS_DSN=
LOG_CHANNEL=stack
LOG_LEVEL=debug

# ─── Ticketing Config ─────────────────────────────────
RESERVATION_TTL_MINUTES=15
PAYMENT_TIMEOUT_SECONDS=180
RECONCILIATION_INTERVAL_MINUTES=5
RECONCILIATION_PENDING_THRESHOLD_MINUTES=10
```

---

## 10. Risk Register

| # | Risk | Impact | Likelihood | Mitigation |
|---|---|---|---|---|
| R1 | Paystack webhook delay/failure | Tickets not issued | Medium | Reconciliation job every 5 min verifies pending payments directly with Paystack |
| R2 | Concurrent checkout on last ticket | Overselling | High | `SELECT FOR UPDATE` + DB check constraint + unique constraints |
| R3 | Late payment after reservation expiry | Overselling or lost payment | Medium | Flag for manual reconciliation; never auto-fulfill without available inventory |
| R4 | Admin account compromise | Full system access | Low | MFA required, session revocation, audit logs, reauthentication for sensitive ops |
| R5 | QR token brute-force | Unauthorized admission | Low | 256-bit entropy tokens, rate-limited check-in endpoint |
| R6 | Webhook replay/forgery | Duplicate processing or fraud | Medium | HMAC-SHA512 signature, deduplication, idempotent processing |
| R7 | Paystack API outage | Checkout unavailable | Low | Timeout handling, circuit breaker, user-facing error message, retry guidance |
| R8 | Database failure | Total service outage | Low | WAL archiving, automated backups, tested restore procedure, RPO 15min |
| R9 | Secret key exposure | Payment system compromise | Low | Backend-only, env injection, never in source/logs/frontend, secret scanning in CI |
| R10 | Fee miscalculation | Revenue loss | Low | Integer arithmetic only, server-side calculation, Paystack amount verification |

---

## 11. Phase Dependencies

```
Phase 0 (Baseline) ──→ Phase 1 (Database) ──→ Phase 2 (Admin Auth) ──→ Phase 3 (Events)
                                                                             │
                                                                             ↓
                                                          Phase 4 (Checkout & Inventory)
                                                                             │
                                                                             ↓
                                                          Phase 5 (Paystack Mobile Money)
                                                                             │
                                                                             ↓
                                                          Phase 6 (Webhooks & Reconciliation)
                                                                             │
                                                                             ↓
                                                          Phase 7 (Ticket Issuance)
                                                                             │
                                                                             ↓
                                                          Phase 8 (Gate Check-In)
                                                                             │
                                                          Phase 2 ──→ Phase 9 (Reports)
                                                                             │
                                                                             ↓
                                                          Phase 10 (Hardening & Release)
```

---

## 12. Definition of Done (Per Phase)

Every phase is complete only when:

- [ ] All deliverables listed are implemented (not placeholders)
- [ ] All listed tests pass
- [ ] Authorization and validation are enforced server-side
- [ ] Failure states are handled
- [ ] No secrets or sensitive data in logs
- [ ] Database migrations included
- [ ] API Resources used for all responses
- [ ] Frontend loading/error/empty/success states present
- [ ] Documentation updated
- [ ] Agent reports: files changed, tests run, known limitations, next steps

---

## 13. Manual Actions Required (Human Tasks)

| # | Action | When Needed | Owner |
|---|---|---|---|
| M1 | Provide Paystack test API keys (pk_test, sk_test) | Phase 5 | Stakeholder |
| M2 | Configure Paystack webhook URL on dashboard | Phase 6 | Stakeholder |
| M3 | Set up email provider (SMTP credentials) | Phase 7 | Stakeholder |
| M4 | Register domain `ticket.clevawave.com` and configure DNS | Phase 10 | Stakeholder |
| M5 | Obtain TLS certificate (or use managed provider) | Phase 10 | Stakeholder |
| M6 | Paystack merchant live verification and live key activation | Pre-production | Stakeholder |
| M7 | Ghana Data Protection Act (Act 843) legal review | Pre-production | Legal counsel |
| M8 | Security assessment / penetration test | Phase 10 | Security reviewer |
| M9 | Backup storage setup (S3-compatible) | Phase 10 | Stakeholder |
| M10 | Monitoring service setup (Sentry account) | Phase 10 | Stakeholder |

---

*This document is the authoritative implementation plan. It is subordinate only to AGENTS.md and the documents in `docs/`. Any deviation from this plan must be documented and justified.*

