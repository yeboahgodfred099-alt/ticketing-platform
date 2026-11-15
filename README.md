# ClevaWave Ticketing Platform

> Secure, production-ready event ticketing platform for Ghana. Customers purchase tickets via Paystack Mobile Money wnithout creating an account. Built with **Laravel 11**, **Next.js 15**, **PostgreSQL 16**, and **Redis 7**.

---

## 1. Architectural Highlights

- **Anonymous Guest Checkout**: Customers never register, log in, or set passwords.
- **Paystack Ghana Mobile Money**: Server-side initiation via Paystack Charge API (`mtn`, `atl`, `vod`).
- **Payment Integrity**: Laravel is the sole authority. Tickets are issued only after independent verification matching transaction reference, amount (pesewas), and currency (`GHS`).
- **Inventory Concurrency Protection**: Atomic `SELECT ... FOR UPDATE` row locks prevent overselling. Expired reservations are automatically released by the scheduler.
- **Single-Use Cryptographic QR Codes**: QR payloads contain only opaque random tokens (SHA-256 hashed at rest). Raw tokens are never logged or stored in plain text.
- **Gate Staff Check-In**: Camera-based scanner (`/staff/scan`) with dual QR hash / ticket number lookup, pessimistic locks preventing race conditions, and real-time gate metrics.
- **Financial & Operational Reporting**: Real-time revenue calculation (gross, Paystack absorbed fees, net), conversion rates, attendance metrics, and data-minimized CSV streaming exports.
- **Role-Based Access Control**: Strict separation between Super Admin, Event Manager, Finance Manager, and Gate Staff, with mandatory MFA (TOTP) for Super Admins.
- **Financial History Immutability**: All monetary calculations use integer minor currency units (pesewas). No floating-point math.

---

## 2. Technology Stack

| Layer | Component | Version |
|---|---|---|
| **Frontend** | Next.js, React, TypeScript (strict), Tailwind CSS | Next.js 15, React 19 |
| **Backend** | Laravel REST API, PHP-FPM | Laravel 11, PHP 8.3 |
| **Database** | PostgreSQL | 16-alpine |
| **Cache & Queues** | Redis | 7-alpine |
| **Reverse Proxy** | Nginx with TLS and security headers | 1.27-alpine |
| **Local Mail** | Mailpit | latest |

---

## 3. Directory Layout

```text
ticketing-platform/
├── AGENTS.md                   # Highest-level agent instructions & rules
├── implementation.md           # Approved phase-by-phase implementation plan
├── docs/                       # Architectural, database, and security specifications
│   ├── ARCHITECTURE.md         # System components and communication
│   ├── DATABASE_DESIGN.md      # Schema, constraints, and tables
│   ├── SECURITY_REQUIREMENTS.md# OWASP ASVS Level 2 requirements
│   ├── PROJECT_SPECIFICATION.md# Functional requirements
│   ├── DEVELOPMENT_WORKFLOW.md # Commit & reporting standards
│   └── OPERATIONAL_RUNBOOK.md  # Deployment, incident response, backups
├── backend/                    # Laravel 11 REST API
│   ├── app/                    # Controllers, Services, Models, Policies, Jobs
│   ├── config/                 # App, database, Sanctum, Paystack configs
│   ├── database/               # Migrations, seeders, factories
│   ├── routes/                 # API & console scheduled routes
│   └── tests/                  # Unit, Feature, and Integration tests
├── frontend/                   # Next.js 15 application
│   ├── app/                    # App router (public, checkout, admin, staff)
│   ├── components/             # Reusable UI & domain components
│   ├── lib/                    # Typed API client, utilities, formatting
│   └── types/                  # Shared TypeScript interfaces
├── infra/                      # Dockerfiles, Nginx configs, backup scripts
│   ├── docker/                 # Production & development Docker configurations
│   └── scripts/                # backup.sh, restore.sh, wait-for-it.sh
├── docker-compose.yml          # Local development stack
├── docker-compose.prod.yml     # Production overrides
└── .env.example                # Canonical environment variable catalog
```

---

## 4. Getting Started (Local Development)

### Prerequisites

- [Docker](https://docs.docker.com/get-docker/) & [Docker Compose](https://docs.docker.com/compose/)
- [Node.js 22+](https://nodejs.org/) & [npm](https://www.npmjs.com/)

### Step 1: Clone and Configure Environment

```bash
cp .env.example .env
```

Build the backend images, generate a Laravel key, and set the generated value
as `APP_KEY` in `.env` before starting the services. Keep the key private and
stable; do not generate a replacement for an existing installation.

```bash
docker compose build backend queue scheduler
docker compose run --rm --no-deps backend php artisan key:generate --show
```

Review `.env` and configure your Paystack test API keys if testing live sandbox payments:
```env
PAYSTACK_PUBLIC_KEY=pk_test_...
PAYSTACK_SECRET_KEY=sk_test_...
PAYSTACK_WEBHOOK_SECRET=
```

### Step 2: Start the Development Containers

```bash
docker compose up -d
```

This spins up:
- **Next.js Frontend**: [http://localhost:3000](http://localhost:3000)
- **Laravel Backend (via Nginx)**: [http://localhost:8000](http://localhost:8000)
- **Mailpit Web UI (Local Email Capture)**: [http://localhost:8025](http://localhost:8025)
- **PostgreSQL 16**: `127.0.0.1:5432`
- **Redis 7**: `127.0.0.1:6379`

### Step 3: Run Database Migrations & Seeds

```bash
docker compose exec backend php artisan migrate --seed
```

### Step 4: Bootstrap the First Super Admin

```bash
docker compose exec backend php artisan admin:create-super
```
Follow the interactive prompt to enter the Super Admin name, email, and password. MFA setup is enforced on first login.

---

## 5. Completed Implementation Phases

- **Phase 0 — Baseline & Infrastructure**: Docker containerization, Nginx, Next.js 15, Laravel 11 skeleton, CI/CD pipeline.
- **Phase 1 — Database & Foundation**: 17 migrations, UUIDs, BIGINT pesewas, strict check constraints, 15 Eloquent models, factories & seeders.
- **Phase 2 — Admin Identity & Permissions**: Sanctum stateful sessions, TOTP MFA, RBAC policies, sensitive audit log scrubbing.
- **Phase 3 — Events & Ticket Catalog**: Event lifecycle machine (draft/published/cancelled), banner uploads, public events catalog.
- **Phase 4 — Anonymous Checkout & Inventory**: Atomic inventory reservations (`lockForUpdate`), 15-minute TTL expiration background job, phone normalization.
- **Phase 5 — Paystack Mobile Money**: Ghana MoMo (`mtn`, `vod`, `atl`) Charge API, USSD prompt handling, fee absorption (1.95%).
- **Phase 6 — Webhooks & Reconciliation**: HMAC-SHA512 verification, deduplication, independent verification, background reconciliation job.
- **Phase 7 — Ticket Issuance & Cryptographic QR**: 256-bit token hashes, dynamic vector QR codes, HTML email with iCalendar `.ics`.
- **Phase 8 — Gate Staff Check-In**: Pessimistic ticket locks, camera scanner, dual QR/ticket number validation, gate audio feedback.
- **Phase 9 — Admin Reports & Audit Logs**: Gross/fee/net financial analytics, chunked CSV exports with phone masking, immutable audit logs.
- **Phase 10 — Production Hardening & Runbooks**: Nginx security headers (CSP, HSTS), AES-256-CBC backup and restore automation scripts, operational runbooks.

---

## 6. Testing & Quality Verification

### Frontend (TypeScript, ESLint, Next.js Build)

```bash
cd frontend
npm run lint
npx tsc --noEmit
npm run build
```

### Backend (Pint, PHPUnit)

```bash
docker-compose exec backend ./vendor/bin/pint --test
docker-compose exec backend php artisan test
```

---

## 7. Operational Runbook & Disaster Recovery

Refer to [`docs/OPERATIONAL_RUNBOOK.md`](docs/OPERATIONAL_RUNBOOK.md) for detailed operational procedures:
- **Zero-downtime deployment**: Step-by-step rollout and cache warming.
- **Incident Response**: Paystack webhook delays, late payments, scan latency.
- **Database Backup & Restoration**: AES-256-CBC encrypted backups (`infra/scripts/backup.sh`) and restore verification (`infra/scripts/restore.sh`).
- **Secrets Management**: Key rotation for Paystack and Laravel.
