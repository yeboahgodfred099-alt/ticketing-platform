# ClevaWave Ticketing Platform

> Secure, production-ready event ticketing platform for Ghana. Customers purchase tickets via Paystack Mobile Money without creating an account. Built with **Laravel 11**, **Next.js 15**, **PostgreSQL 16**, and **Redis 7**.

---

## 1. Architectural Highlights

- **Anonymous Guest Checkout**: Customers never register, log in, or set passwords.
- **Paystack Ghana Mobile Money**: Server-side initiation via Paystack Charge API (`mtn`, `atl`, `vod`).
- **Payment Integrity**: Laravel is the sole authority. Tickets are issued only after independent verification matching transaction reference, amount (pesewas), and currency (`GHS`).
- **Inventory Concurrency Protection**: Atomic `SELECT ... FOR UPDATE` row locks prevent overselling. Expired reservations are automatically released by the scheduler.
- **Single-Use Cryptographic QR Codes**: QR payloads contain only opaque random tokens (SHA-256 hashed at rest). Raw tokens are never logged or stored in plain text.
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

Review `.env` and configure your Paystack test API keys if testing live sandbox payments:
```env
PAYSTACK_PUBLIC_KEY=pk_test_...
PAYSTACK_SECRET_KEY=sk_test_...
```

### Step 2: Start the Development Containers

```bash
docker-compose up -d
```

This spins up:
- **Next.js Frontend**: [http://localhost:3000](http://localhost:3000)
- **Laravel Backend (via Nginx)**: [http://localhost:8000](http://localhost:8000)
- **Mailpit Web UI (Local Email Capture)**: [http://localhost:8025](http://localhost:8025)
- **PostgreSQL 16**: `127.0.0.1:5432`
- **Redis 7**: `127.0.0.1:6379`

### Step 3: Run Database Migrations & Seeds

```bash
docker-compose exec backend php artisan migrate --seed
```

### Step 4: Bootstrap the First Super Admin

```bash
docker-compose exec backend php artisan admin:create-super
```
Follow the interactive prompt to enter the Super Admin name, email, and password. MFA setup is enforced on first login.

---

## 5. Health Checks

- **Laravel API Health**: `GET http://localhost:8000/api/v1/health`
- **Next.js Health**: `GET http://localhost:3000/api/health`

---

## 6. Testing & Quality Verification

### Frontend (TypeScript, ESLint, Build)

```bash
cd frontend
npm run lint
npx tsc --noEmit
npm run build
```

### Backend (Pint, PHPStan, PHPUnit)

```bash
docker-compose exec backend ./vendor/bin/pint --test
docker-compose exec backend ./vendor/bin/phpstan analyse
docker-compose exec backend php artisan test
```

---

## 7. Security Standards & ASVS Level 2

Refer to `docs/SECURITY_REQUIREMENTS.md` and `implementation.md` for the complete security invariants, webhook signature verification specifications, and audit logging policies.
