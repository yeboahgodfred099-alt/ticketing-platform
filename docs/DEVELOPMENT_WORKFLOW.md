# Development Workflow — AI Agent and Engineering Team

## 1. Purpose

Build the platform in tested, reviewable phases. Do not generate the entire application in one prompt or claim production readiness from a successful local preview.

## 2. Before starting

1. Read `AGENTS.md` and all relevant documents in `docs/`.
2. Inspect the repository, existing files, framework versions and package manifests.
3. Identify whether this is a new project or an existing codebase.
4. Confirm the current official Paystack documentation before payment work.
5. Identify unknowns and ask only questions that block safe implementation.
6. Produce a concise implementation plan with files, migrations, endpoints and tests.

Do not overwrite an existing project or replace established conventions without inspection and explanation.

## 3. Recommended repository layout

```text
project-root/
├── AGENTS.md
├── docs/
│   ├── PROJECT_SPECIFICATION.md
│   ├── ARCHITECTURE.md
│   ├── DATABASE_DESIGN.md
│   ├── SECURITY_REQUIREMENTS.md
│   └── DEVELOPMENT_WORKFLOW.md
├── frontend/
│   ├── app/
│   ├── components/
│   ├── features/
│   ├── lib/
│   ├── hooks/
│   ├── types/
│   └── tests/
├── backend/
│   ├── app/
│   ├── database/
│   ├── routes/
│   ├── tests/
│   └── ...
├── infra/
│   ├── docker/
│   ├── nginx/
│   └── scripts/
├── docker-compose.yml
├── .env.example
└── README.md
```

The exact layout may be adjusted to match the chosen monorepo approach, but frontend, backend, infrastructure and project documentation must remain clear.

## 4. Development phases

### Phase 0 — Repository and architecture baseline
Deliver:
- Repository inspection report.
- Framework/runtime versions.
- Architecture decisions.
- Environment variable inventory.
- Local development instructions.
- Initial threat model.
Tests:
- Clean install/build.
- Health checks.

### Phase 1 — Foundation and database
Deliver:
- Next.js application with TypeScript strict mode.
- Laravel API.
- PostgreSQL and Redis.
- Docker Compose for local development.
- Migrations, factories and seeders.
- Health endpoints.
Tests:
- Migration from empty database.
- Migration rollback where safe.
- Model relationship tests.
- Database constraints.

### Phase 2 — Admin identity and permissions
Deliver:
- Admin login/logout.
- Secure sessions and CSRF.
- MFA.
- Roles and permissions.
- Admin creation/disable workflow.
- Audit logging.
Tests:
- Authentication.
- MFA enforcement.
- Session expiry/revocation.
- Role and permission enforcement.
- Privilege escalation and IDOR tests.

### Phase 3 — Events and ticket catalog
Deliver:
- Event CRUD and lifecycle.
- Ticket type CRUD.
- Public event listing/details.
- Sale windows and limits.
- Admin screens.
Tests:
- Published-only public visibility.
- Invalid sale window.
- Quantity/price validation.
- Historical snapshot behavior.
- Permission tests.

### Phase 4 — Anonymous checkout and inventory
Deliver:
- Customer checkout UI.
- Server-side price calculation.
- Order/order-item creation.
- Inventory reservation.
- Reservation expiration job.
Tests:
- Guest checkout without account.
- Client price tampering.
- Quantity limits.
- Concurrent last-ticket purchase.
- Reservation expiration/release.
- No overselling.

### Phase 5 — Paystack Mobile Money
Deliver:
- Paystack backend adapter.
- Charge initiation.
- Provider/network selection based on current API docs.
- Action-required/OTP continuation only where required.
- Payment state mapping.
- Secure status endpoint.
Tests:
- Paystack test-mode integration.
- Pending/success/failure/expired.
- Incorrect amount/currency/reference.
- Timeout and uncertain response.
- No PIN collection or OTP persistence.

### Phase 6 — Webhooks and reconciliation
Deliver:
- Raw-body signature validation.
- Webhook event persistence/deduplication.
- Queue processing.
- Independent transaction verification.
- Scheduled reconciliation.
- Operational exception reporting.
Tests:
- Invalid signature rejected.
- Duplicate event idempotent.
- Replayed event harmless.
- Out-of-order events.
- Provider timeout/retry.
- Successful payment without webhook recovered by reconciliation.

### Phase 7 — Ticket issuance and retrieval
Deliver:
- Unique ticket per admission.
- Cryptographically random QR token.
- Hashed token storage.
- Ticket confirmation page.
- Email delivery.
- Secure ticket retrieval.
Tests:
- Exact ticket count.
- Duplicate payment processing.
- Token entropy/lookup.
- Retrieval authorization.
- Notification failure does not undo ticket issuance.

### Phase 8 — Gate validation
Deliver:
- Staff interface.
- Event assignment.
- QR scanning.
- Atomic check-in.
- Check-in reporting.
Tests:
- Valid ticket.
- Invalid token.
- Wrong event.
- Cancelled/refunded ticket.
- Duplicate scan.
- Two scanners race on one ticket.
- Unauthorized staff access.

### Phase 9 — Admin reports and operational tools
Deliver:
- Sales/payment/ticket reports.
- CSV export with permissions.
- Payment reconciliation dashboard.
- Audit log viewer.
- System health/queue status.
Tests:
- Report calculations.
- Permission scoping.
- Export data minimization.
- Large result pagination.

### Phase 10 — Hardening and release
Deliver:
- Production Docker/Nginx configuration.
- TLS and security headers.
- Secrets management.
- Backups and restore runbook.
- Monitoring and alerts.
- CI pipeline.
- Deployment and rollback instructions.
- Security assessment report.
Tests:
- Full regression suite.
- SAST/dependency/container scans.
- Load and concurrency tests.
- Backup restoration.
- Staging end-to-end tests.
- Production smoke tests with approved live test procedure.

## 5. Definition of done for each task

A task is complete only when:
- Requirements are understood.
- Implementation is complete, not a placeholder.
- Authorization and validation are enforced server-side.
- Failure states are handled.
- Relevant tests pass.
- No secrets or sensitive data are logged.
- Database migrations and documentation are updated.
- Frontend loading/error/empty/success states are present where applicable.
- The agent reports files changed, tests run and known limitations.

## 6. Code quality

Frontend:
- TypeScript strict mode.
- Reusable components.
- Accessible controls and keyboard navigation.
- Centralized typed API client.
- Avoid duplicated business rules.
- No secrets in `NEXT_PUBLIC_*` variables.
- No authoritative pricing/payment logic in the browser.

Backend:
- Thin controllers.
- Form Request validation.
- Policies/Gates.
- Services/actions for business operations.
- Explicit transactions and locks.
- API Resources.
- Typed enums/value objects where useful.
- Clear exception handling.
- Avoid giant controllers and generic catch-all services.

Database:
- Migrations for all changes.
- Foreign keys and unique/check constraints.
- Indexes based on query needs.
- No floating-point money.
- Preserve financial history.
- Test concurrency behavior.

## 7. CI pipeline

Run on pull request:
- Frontend lint.
- Frontend type check.
- Frontend unit tests.
- Frontend production build.
- Backend formatter check.
- Backend static analysis.
- Backend unit/feature tests.
- Migration test.
- Dependency vulnerability scan.
- Secret scan.
- Container scan where configured.

Block merge on failed required checks.

## 8. Environment variables

Create `.env.example` with variable names and safe placeholders only. Never commit real secrets.

Expected categories:
- Application environment and URLs.
- PostgreSQL connection.
- Redis connection.
- Paystack public key (only if needed by a supported public integration component) and secret key (backend only).
- Email provider.
- Object storage.
- Session/cookie domains.
- Monitoring DSN.
- Queue configuration.

Do not put real credentials in documentation, seeders, tests or frontend code.

## 9. Documentation deliverables

Maintain:
- Root README with setup and commands.
- API/OpenAPI documentation.
- Database migration notes.
- Paystack integration notes and source links/date checked.
- Environment setup.
- Deployment and rollback runbook.
- Backup/restore runbook.
- Security controls and exceptions register.
- Test plan and test results.
- Incident response contacts/procedure.

## 10. AI agent reporting format

At the end of each work session, report:

### Completed
Specific implemented functionality.

### Files changed
List relevant files.

### Database changes
Migrations and constraints added.

### Tests
Commands run and actual pass/fail results. Never invent results.

### Security considerations
Controls implemented and any unresolved risks.

### Remaining work
Specific next tasks and blockers.

### Manual actions required
Credentials, provider dashboard settings, DNS, merchant verification or approvals that a human must complete.

## 11. Release gates

Do not deploy live payments until:
- Merchant account is approved and live channels are confirmed.
- Live payment flow has been tested under an approved controlled process.
- Webhook endpoint and signature validation are verified.
- Payment reconciliation is operational.
- Ticket inventory and issuance concurrency tests pass.
- Admin MFA and authorization tests pass.
- Backups and restoration are verified.
- Privacy/legal review is completed.
- No unresolved critical/high security finding remains without formally accepted mitigation.
