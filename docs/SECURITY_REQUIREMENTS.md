# Security Requirements — The Armor

## 1. Security objectives

Protect:
- Customer personal information.
- Administrator and staff accounts.
- Payment integrity.
- Ticket inventory.
- Ticket issuance and admission.
- Financial and audit records.
- Application availability and recovery.

Use OWASP ASVS Level 2 as the baseline verification framework. Conduct a threat model and risk assessment before production.

## 2. Threat model

Key threats include:
- Forged or replayed payment webhooks.
- Fake frontend success/redirect.
- Payment amount or currency tampering.
- Duplicate payment processing.
- Duplicate ticket issuance.
- Overselling through concurrent checkout.
- Unauthorized admin access.
- Privilege escalation.
- IDOR/object-level authorization flaws.
- Brute-force login and checkout abuse.
- QR token guessing, copying or replay.
- Duplicate gate scans.
- SQL injection, XSS, CSRF and malicious uploads.
- Secret leakage through source control, frontend bundles or logs.
- Database theft, backup exposure and server compromise.
- Provider/network outages and delayed payment callbacks.

## 3. Identity and access management

### Admin/staff
- Require individual accounts; no shared admin accounts.
- MFA required for Super Admin and strongly recommended/required for all privileged staff.
- Use a maintained Laravel-compatible MFA implementation.
- Passwords hashed with Argon2id where supported, or a secure framework-supported alternative.
- Rate-limit login and password reset.
- Use generic login errors to reduce account enumeration.
- Verify email before privileged account activation.
- Secure password reset with short-lived, single-use tokens.
- Revoke sessions on logout, account disablement and password/security changes.
- Require reauthentication/MFA for high-risk actions.
- Apply least privilege and deny-by-default authorization.
- Review administrator access periodically.

### Customer
- No account or password.
- Secure ticket/order retrieval must use high-entropy access tokens or verified contact challenge.
- Do not treat order number, UUID, email address or phone number alone as proof of ownership.
- Rate-limit retrieval and OTP delivery/verification.
- Prevent enumeration through uniform responses and bounded retries.

## 4. Session and browser security

- HTTPS only.
- Secure, HttpOnly cookies.
- Appropriate SameSite setting.
- Correct Sanctum stateful-domain and CSRF configuration.
- Rotate session identifier after login and privilege changes.
- Idle and absolute session expiry.
- Revoke sessions server-side.
- Do not store privileged bearer tokens in localStorage.
- Strict CORS allowlist; never wildcard credentialed origins.
- Apply Content Security Policy.
- Apply HSTS after HTTPS deployment is confirmed.
- Set X-Content-Type-Options, Referrer-Policy and frame-ancestor restrictions.
- Do not cache private admin/payment/ticket pages in shared caches.

## 5. API and application security

- Validate every request on the backend.
- Use Laravel Form Requests.
- Use Policies/Gates for every protected resource.
- Check object ownership/tenant/event assignment on every request.
- Prevent mass assignment; define fillable/guarded fields carefully.
- Use Eloquent or parameterized SQL.
- Escape output; sanitize rich text.
- Avoid rendering untrusted HTML.
- Use safe file upload handling and re-encode images.
- Apply request size limits and content-type validation.
- Apply rate limits by endpoint, IP and relevant identifier.
- Use pagination with maximum page sizes.
- Avoid leaking stack traces or internal details in production.
- Use correlation/request IDs.
- Return minimal API data through Resources.

## 6. Paystack security

Before implementation, consult current official Paystack documentation. Confirm the active Ghana Mobile Money charge flow, provider codes, API request/response formats, OTP actions, webhook signature scheme and verification response fields.

Mandatory:
- Secret key backend-only.
- Store keys in secrets manager or protected environment injection.
- Never expose secret keys in frontend or logs.
- Never collect/store Mobile Money PIN.
- Do not persist OTP.
- Validate webhook signature against exact raw body using current documented method.
- Use constant-time signature comparison.
- Reject invalid signatures.
- Verify transaction with Paystack before fulfillment.
- Match reference, amount, currency and expected order/payment.
- Treat browser redirect as display-only.
- Use idempotent processing.
- Handle duplicate/out-of-order events.
- Reconcile unresolved transactions.
- Do not blindly retry a charge when outcome is uncertain.
- Sanitize provider responses before storage/logging.
- Protect payment endpoints against abuse.

Never assume OTP/PIN steps are identical across networks. Follow the actual Paystack response and supported flow. The system must not create a custom Mobile Money PIN form.

## 7. Webhook security and reliability

- Dedicated webhook endpoint.
- Read raw request bytes before JSON transformation for signature validation.
- Verify signature before trusting payload.
- Deduplicate events using documented provider identifiers where available.
- Persist receipt safely before asynchronous processing.
- Return appropriate acknowledgement promptly.
- Process in a queue with retries/backoff.
- Verify transaction independently.
- Record state transitions.
- Make handler idempotent under concurrent delivery.
- Do not assume event order.
- Alert on repeated failures or invalid signatures.
- Never log secrets or sensitive authentication values.

## 8. Payment, order and inventory integrity

- Backend calculates prices and totals.
- Store money as integer minor units.
- Use database transactions and row-level locks.
- Reserve inventory atomically.
- Expire reservations via backend scheduler.
- Convert reservation to sold quantity only on verified payment.
- Issue tickets exactly once.
- Enforce unique constraints.
- Handle late success after reservation expiry through explicit reconciliation and approved fulfillment/refund policy.
- Do not let ordinary admin users mark an order paid.
- Reversals/refunds must be represented as auditable state transitions.
- Reconcile provider transactions with internal records.

## 9. QR and check-in security

- Use cryptographically secure random tokens with sufficient entropy.
- QR contains only opaque token or secure URL/token; no PII, payment data or sequential IDs.
- Store token hash only.
- Never log raw QR token.
- Validate server-side over HTTPS.
- Require staff authentication and event assignment.
- Lock ticket row during check-in.
- Enforce one check-in per ticket with a database unique constraint.
- Return clear invalid/already-used/wrong-event/cancelled outcomes.
- Initial release is online-only; do not claim offline validation security.

## 10. Database and infrastructure security

- PostgreSQL and Redis private-network only.
- Do not expose database ports publicly.
- Separate migration role from runtime role.
- Runtime role has minimum required permissions.
- Strong unique credentials.
- TLS for service connections where supported.
- Encrypt backups and restrict backup access.
- Test restore regularly.
- Patch operating system, runtime, dependencies and containers.
- Disable unnecessary services.
- SSH keys; disable root SSH login and password SSH where operationally feasible.
- Firewall allowlist only required ports.
- Run containers as non-root where practical.
- Pin image versions and scan images.
- Keep secrets outside source control.
- Separate dev/staging/production credentials and data.
- Use private object storage and least-privilege access.

## 11. File security

- Allowlist file types.
- Check actual MIME/content, not only extension.
- Limit size and dimensions.
- Re-encode image uploads.
- Generate random filenames.
- Store uploads outside executable web roots or in object storage.
- Scan files for malware where available.
- Prevent path traversal.
- Restrict access to private files.
- Never allow uploaded executable scripts to run.

## 12. Logging, audit and monitoring

Record:
- Admin authentication successes/failures.
- Permission and role changes.
- Event/ticket configuration changes.
- Order/payment state transitions.
- Webhook verification/processing outcomes.
- Ticket issuance.
- Check-in.
- Refund/reversal actions.
- Security-relevant errors.

Do not log:
- Passwords.
- OTPs.
- PINs.
- Session cookies.
- Authorization headers.
- API secrets.
- Raw QR tokens.
- Unnecessary full personal/payment payloads.

Monitor:
- Failed logins.
- Invalid webhook signatures.
- Payment mismatches.
- Queue failures.
- Pending transactions beyond expected windows.
- Successful payments without issued tickets.
- Inventory inconsistencies.
- Repeated QR scans.
- Unusual checkout volume.
- Database/Redis/server health.

Use Sentry or equivalent for exceptions and centralized logs/alerts for operational events.

## 13. Backups and recovery

- Automated database backups.
- PostgreSQL WAL archiving/PITR where supported.
- Encrypted offsite backups.
- Uploaded-file backup/replication.
- Separate backup credentials.
- Document RPO and RTO.
- Test restoration on a schedule.
- Document incident and disaster recovery procedures.
- Do not treat an untested backup as reliable.

Suggested initial targets (must be validated): RPO 15 minutes; RTO 4 hours.

## 14. Privacy and Ghana compliance

- Assess obligations under Ghana's Data Protection Act, 2012 (Act 843).
- Confirm applicable Data Protection Commission registration/compliance requirements.
- Publish clear privacy notice and terms.
- Identify controller/processor roles.
- Collect only necessary personal data.
- Define lawful purpose and retention.
- Restrict access and provide processes for data subject requests.
- Have a breach response procedure.
- Review processor agreements and cross-border data transfers.
- Obtain qualified legal/compliance advice; this document is not legal advice.

## 15. Security testing gate

Before production:
- Threat model reviewed.
- OWASP ASVS Level 2 verification checklist completed or exceptions documented.
- Dependency and container scans completed.
- SAST and secret scan completed.
- Authentication/authorization tests pass.
- IDOR tests pass.
- XSS/CSRF/injection tests pass.
- Webhook forgery/replay/duplicate tests pass.
- Payment mismatch tests pass.
- Inventory concurrency tests pass.
- QR double-scan tests pass.
- Backup restore test passes.
- Staging penetration test or independent security review completed for material risk.
- No unresolved critical/high findings without formally accepted mitigation.
