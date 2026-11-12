# Project Specification — Secure Mobile Money Ticketing Platform

## 1. Product overview

Build a multi-event ticketing platform for events in Ghana. Customers can discover events, select ticket categories, provide contact details and pay using Paystack Mobile Money without creating an account.

Authenticated administrators manage events, ticket inventory, orders, payments, attendees, reports and system configuration. Authorized gate staff scan and validate individual tickets.

## 2. Goals

- Make ticket purchase simple on mobile and desktop.
- Support Paystack Ghana Mobile Money channels enabled for the merchant account.
- Issue tickets only after independently verified successful payment.
- Prevent overselling, duplicate fulfillment and duplicate entry.
- Provide secure administrative management and auditable financial records.
- Support multiple events from the first release.

## 3. Out of scope for initial release

Unless separately approved, do not implement:
- Customer accounts or customer passwords.
- A marketplace with independent seller settlements.
- Native iOS/Android apps.
- Offline QR validation with eventual synchronization.
- Ticket resale or transfer.
- Complex seating maps.
- Automated refunds without an explicit approved refund workflow.

Design extension points without building unnecessary complexity.

## 4. User types

### Public customer
No account or login. Can view published events, select available tickets, checkout, pay and retrieve tickets through a secure proof-of-possession flow.

### Super Admin
Full administrative access, including managing administrators, roles, events, ticket types, orders, payments, reports and settings. MFA required.

### Event Manager
Can manage assigned events and their ticket types, subject to permissions.

### Finance Manager
Can view payment records, reconciliation and financial reports. Cannot silently alter payment outcomes.

### Gate Staff
Can access assigned events and validate tickets. Cannot manage payments or event configuration.

Use role-based access control and server-side authorization. Do not rely on hiding frontend controls.

## 5. Customer journeys

### 5.1 Discover event
- Homepage lists only published and publicly visible events.
- Search/filter may be provided.
- Event page displays title, description, banner, venue, local date/time, sales status and ticket types.
- Unpublished, archived or cancelled events must not be purchasable.

### 5.2 Select tickets
- Customer selects ticket type and quantity.
- Backend revalidates sale window, status, limits and availability.
- Display price and total calculated by backend.
- Prevent quantities outside configured minimum/maximum.

### 5.3 Anonymous checkout
Collect only necessary information:
- Customer/lead name.
- Email address.
- Mobile phone number.
- Selected ticket details.

Do not create a customer login or password. Normalize phone numbers to a consistent international format and validate Ghanaian numbers appropriately without assuming a network solely from a prefix where number portability makes that unreliable.

Show a clear order summary, fees if applicable, total and terms/privacy notice.

### 5.4 Payment
- Customer chooses a supported Mobile Money channel/provider.
- Backend creates order and inventory reservation.
- Backend initiates Paystack charge.
- UI shows actionable statuses such as awaiting authorization, processing, successful, failed or expired.
- Any OTP continuation is shown only when required by the actual Paystack response.
- Never ask for or handle a customer's Mobile Money PIN.
- Customer can safely refresh or close the browser; backend payment processing continues independently.

### 5.5 Ticket delivery and retrieval
- After verified payment, create one ticket per admission.
- Show confirmation page with event and ticket details.
- Send ticket to the provided email where delivery is configured.
- Provide secure ticket retrieval using a high-entropy, unguessable, expiring or revocable access mechanism. Do not use a sequential order number as authentication.
- Consider email/phone OTP for recovery, with rate limits and abuse protection.
- Allow re-delivery without creating another ticket.

## 6. Admin capabilities

### Dashboard
Show accurate, permission-scoped metrics:
- Events active/upcoming.
- Tickets sold and remaining.
- Orders by status.
- Payments by status.
- Gross paid amount and refunds/reversals separately.
- Recent activity.
- Sales trend.

Define all metric calculations and timezone/currency behavior. Do not label pending payments as revenue.

### Event management
- Create, edit, publish, unpublish, cancel and archive events.
- Manage event title, slug, description, banner, venue, address, start/end times, sales window and status.
- Preserve historical order/ticket information when an event changes.
- Prevent unsafe deletion when financial records or tickets exist; prefer archive/cancel workflows.

### Ticket type management
- Create and edit ticket categories.
- Configure price, currency, total quantity, sale window, per-order limits and status.
- Prevent quantity reductions below sold plus active reserved quantity.
- Preserve price/name snapshots on order items.
- Price changes apply to future orders only.

### Orders and payments
- Search/filter by order number, customer details, event, date and status.
- Show order items and payment attempts.
- Never expose secrets or full sensitive provider payloads.
- Manual adjustments/refunds, if later enabled, must require specific permission, reason, confirmation and audit record.
- Admins must not have a casual “mark paid” action.

### Attendees and check-in
- Search tickets and attendees with access controls.
- Display ticket status and check-in state.
- Allow staff to scan only events to which they are assigned.

### Audit and reporting
- Filter and export permitted reports.
- Record administrative changes, actor, timestamp, target and reason where relevant.
- Protect audit records from ordinary editing/deletion.

## 7. Ticket lifecycle

Suggested ticket states:
- `active`
- `checked_in`
- `cancelled`
- `refunded` (only if refund policy and workflow require it)

A ticket is not valid merely because its QR code can be decoded. The backend must validate token, event, ticket state and check-in state.

Each admission has an individual ticket and individual QR token.

## 8. Order and payment lifecycle

Order states should be explicit and documented, for example:
- `pending_payment`
- `paid`
- `expired`
- `cancelled`
- `partially_refunded` (if supported)
- `refunded`

Payment attempts should have their own states, for example:
- `initiated`
- `pending`
- `requires_action`
- `success`
- `failed`
- `abandoned`
- `expired`
- `reversed`

Do not assume every provider status maps directly to an internal state. Create a documented mapping based on current Paystack responses.

An order may have multiple payment attempts, but must only be fulfilled once for the correct paid amount. Define and test the policy for late success after reservation expiry; never issue beyond inventory or silently discard a confirmed charge. Escalate ambiguous late-payment cases into reconciliation/manual review and an approved refund or fulfillment process.

## 9. Notifications

Initial:
- Ticket confirmation email.
- Payment/order receipt email.
- Admin notification for payment/reconciliation exceptions.

Optional later:
- SMS/WhatsApp notification.
- Reminder before event.
- Cancellation/refund notifications.

Notifications must be queued and retried safely. Notification failure must not roll back a successful payment or ticket issuance.

## 10. Business rules

- Currency initially GHS, with a data model that does not assume all future events use the same currency.
- Store amounts as integer minor units (pesewas for GHS).
- The server calculates all prices and totals.
- Ticket quantity must be positive and within configured limits.
- Sale windows and event times use timezone-aware timestamps; store UTC and display Africa/Accra time.
- Customer checkout is guest-only.
- No ticket is issued for pending, failed, expired or unverified payment.
- One ticket represents one admission unless the product explicitly defines a group ticket.
- QR validation is online-only for initial release.
- Payment reversals and refunds require defined state transitions and auditability.

## 11. Acceptance criteria

The initial release is accepted only when:
1. Public customers can purchase without registering.
2. Admins can securely create and publish multiple events and ticket types.
3. Backend price and inventory checks cannot be bypassed by client changes.
4. Paystack Mobile Money flow works for each channel enabled on the merchant account in staging and approved live tests.
5. Pending payment never issues tickets.
6. Verified successful payment issues exactly the purchased number of tickets.
7. Duplicate webhook/retry cannot duplicate tickets.
8. Concurrent checkout cannot oversell inventory.
9. Secure ticket retrieval does not expose another customer's order.
10. QR double-scan cannot admit the same ticket twice.
11. Admin permissions are enforced server-side.
12. Payment, inventory and admin actions are auditable.
13. Automated tests and staging acceptance tests pass.
14. Backup restoration and operational recovery are demonstrated.
