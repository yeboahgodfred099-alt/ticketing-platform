# Operational Runbook — ClevaWave Event Ticketing Platform

This runbook outlines operational procedures, incident response playbooks, disaster recovery steps, and deployment strategies for `ticket.clevawave.com`.

---

## 1. System Overview & Architecture

- **Domain**: `ticket.clevawave.com`
- **Frontend**: Next.js 15, React 19, TypeScript strict mode, Tailwind CSS.
- **Backend API**: Laravel 11 REST API, PHP 8.3-FPM.
- **Database**: PostgreSQL 16 (Authoritative store; integer minor currency units in pesewas).
- **Cache & Distributed Locks**: Redis 7.
- **Payment Gateway**: Paystack Ghana Mobile Money (Charge API for `mtn`, `vod`, `atl`).
- **Security Baseline**: OWASP ASVS Level 2 compliance.

---

## 2. VPS & Docker Manager Deployment Guide

### 2.1 Prerequisites on the VPS

1. **Docker & Docker Compose**: Ensure Docker 26+ and Docker Compose v2+ are installed.
2. **Domain DNS**: An `A` record pointing `ticket.clevawave.com` to your VPS public IPv4 address.
3. **Ports**: Ensure ports `80` (HTTP) and `443` (HTTPS) are open in your VPS firewall / cloud security group (UFW, iptables, AWS SG).

---

### 2.2 Method A: Deployment via Shell / SSH (Recommended)

1. **Clone the repository on the VPS**:
   ```bash
   git clone https://github.com/your-org/ticketing-platform.git /opt/ticketing-platform
   cd /opt/ticketing-platform
   ```

2. **Prepare the production environment configuration**:
   ```bash
   cp .env.production.example .env
   nano .env
   ```
   Configure the following critical values:
   - `DB_PASSWORD`: Set a strong random password.
   - `REDIS_PASSWORD`: Set a strong random password.
   - `APP_KEY`: Generate using `docker run --rm -v $(pwd)/backend:/var/www/html php:8.3-cli php /var/www/html/artisan key:generate --show` or set directly.
   - `PAYSTACK_PUBLIC_KEY` & `PAYSTACK_SECRET_KEY`: Your live Paystack credentials.
   - `MAIL_*`: Your SMTP server credentials (SendGrid, Mailgun, Postmark).

3. **Run the automated deployment script**:
   ```bash
   chmod +x infra/scripts/deploy.sh
   ./infra/scripts/deploy.sh
   ```

4. **Bootstrap the First Super Administrator**:
   ```bash
   docker compose -f docker-compose.prod.yml exec backend php artisan admin:create-super
   ```
   Enter the super administrator's name, email, and password. Multi-factor authentication (TOTP) setup will be enforced upon first login.

5. **SSL Certificate Setup (Let's Encrypt / Certbot)**:
   If your VPS does not have an external reverse proxy (like Cloudflare, Traefik, or Nginx Proxy Manager):
   ```bash
   # Install certbot on the host or run certbot in docker
   sudo certbot certonly --standalone -d ticket.clevawave.com
   # Place the certificates into infra/certs/
   mkdir -p infra/certs
   sudo cp /etc/letsencrypt/live/ticket.clevawave.com/fullchain.pem infra/certs/cert.pem
   sudo cp /etc/letsencrypt/live/ticket.clevawave.com/privkey.pem infra/certs/key.pem
   docker compose -f docker-compose.prod.yml restart nginx
   ```

---

### 2.3 Method B: Deployment via Portainer / Docker Manager Web UI

1. Log into your **Portainer / Docker Manager** web console.
2. Navigate to **Stacks** → **Add Stack**.
3. Name the stack: `ticketing-platform`.
4. Select **Repository** or **Web editor**:
   - If using **Repository**:
     - Repository URL: `https://github.com/your-org/ticketing-platform.git`
     - Compose path: `docker-compose.prod.yml`
   - If using **Web editor**:
     - Paste the contents of `docker-compose.prod.yml`.
5. Under **Environment variables**, click **Load variables from .env** and paste the contents of `.env.production.example` (with your production passwords and Paystack keys).
6. Click **Deploy the stack**.
7. Once containers are running, open the console of the `ticketing_prod_backend` container and run:
   ```bash
   php artisan migrate --force
   php artisan db:seed --class=RolePermissionSeeder --force
   php artisan config:cache
   php artisan route:cache
   php artisan admin:create-super
   ```

---

### 2.4 Configuring the Paystack Webhook

In your [Paystack Dashboard](https://dashboard.paystack.com/#/settings/developer):
1. Navigate to **Settings** → **API Keys & Webhooks**.
2. Set **Live Webhook URL** to:
   ```text
   https://ticket.clevawave.com/api/v1/webhooks/paystack
   ```
3. Copy your **Secret Key** and set it as `PAYSTACK_SECRET_KEY` in your `.env`.

---

## 3. Incident Response Playbooks

### Playbook A: Paystack Webhook Failure or Latency Lag

- **Symptoms**: Customers approve Mobile Money prompt on phone, but order remains in `pending_payment` on the status page.
- **Root Cause Check**:
  - Check Paystack dashboard Webhook logs for HTTP 5xx or connection timeouts.
  - Check `webhook_events` table in PostgreSQL:
    ```sql
    SELECT * FROM webhook_events WHERE status = 'failed' ORDER BY created_at DESC LIMIT 10;
    ```
- **Mitigation & Self-Healing**:
  - The built-in **`payments:reconcile`** scheduler runs every 5 minutes:
    - Finds payments in `pending` older than 5 minutes.
    - Directly queries Paystack's authoritative `GET /transaction/verify/{reference}`.
    - Atomically converts reservations and fulfills tickets once verified.
  - **Manual Trigger**:
    ```bash
    docker compose -f docker-compose.prod.yml exec backend php artisan payments:reconcile
    ```

### Playbook B: Late Payment After Reservation Expiration

- **Symptoms**: Customer took longer than 15 minutes to authorize USSD prompt, causing the reservation to expire before Paystack issued `success`.
- **Handling**:
  - In `OrderFulfillmentService.php`, late payments are inspected:
    - If inventory is still available: reservation is re-acquired and tickets issued.
    - If inventory is exhausted (event sold out): order transitions to `PENDING_MANUAL_REVIEW`, and payment is flagged for administrative refund or organizer escalation.
  - **Admin Resolution**:
    - Navigate to `/admin/reports` or check `orders` with `status = 'pending_manual_review'`.
    - Initiate Paystack refund or allocate organizer reserve tickets.

### Playbook C: Suspected Webhook Replay or Forgery Attack

- **Protection**:
  - All webhooks require an HMAC-SHA512 signature matching the server-side secret key (`hash_equals()` in `WebhookSignatureValidator.php`).
  - Webhooks are deduplicated against the `webhook_events` table (`UNIQUE(provider, event_identifier)`).
  - Duplicate deliveries immediately return HTTP 200 without duplicate charges or ticket issuance.
- **Log Inspection**:
  ```bash
  docker compose -f docker-compose.prod.yml exec backend grep "Invalid Paystack webhook signature" /var/log/nginx/error.log
  ```

### Playbook D: Gate Staff Offline / High Latency at Venue

- **Symptoms**: Gate scanner reports network error or slow responses during attendee check-in.
- **Resolution**:
  - The Gate Scanner (`/staff/scan`) caches scanned ticket IDs locally in memory to prevent duplicate requests.
  - Staff may fall back to cellular hotspot (MTN / Telecel LTE).
  - If venue connectivity drops entirely, staff can query ticket numbers or utilize the attendee CSV export downloaded in advance (`/admin/reports`).

---

## 4. Disaster Recovery & Database Restoration

- **RPO Target**: 15 minutes.
- **RTO Target**: 4 hours.

### Automated Backups
Run daily (or every 15 minutes via cron):
```bash
docker compose -f docker-compose.prod.yml exec backend /var/www/html/infra/scripts/backup.sh
```
Encrypted archives (`ticketing_YYYYMMDD_HHMMSSZ.sql.gz.enc`) are stored with AES-256-CBC encryption.

### Restoration Procedure
To restore onto a fresh PostgreSQL instance or staging server:
```bash
export BACKUP_ENCRYPTION_KEY="<production-backup-secret>"
./infra/scripts/restore.sh /var/backups/ticketing/ticketing_20261113_120000Z.sql.gz.enc ticketing_restored
```
The script validates row counts on `events`, `orders`, and `tickets` upon completion.

---

## 5. Secrets Management & Key Rotation

1. **Paystack Secret Key Rotation**:
   - Update `PAYSTACK_SECRET_KEY` and `PAYSTACK_WEBHOOK_SECRET` in `.env`.
   - Update webhook URL in Paystack Dashboard if needed.
   - Run `docker compose -f docker-compose.prod.yml exec backend php artisan config:cache`.
2. **Admin Password / Credential Reset**:
   - Super admins can manage or disable accounts at `/admin/users`.
   - Force password re-hash using Argon2id.
3. **Application Encryption Key (`APP_KEY`)**:
   - If rotating `APP_KEY`, re-encrypt existing MFA TOTP secrets using `Crypt::encryptString`.

---

## 6. CLI Administration Cheat Sheet

| Command | Purpose |
|---|---|
| `docker compose -f docker-compose.prod.yml exec backend php artisan admin:create-super` | Provision a new Super Administrator account interactively |
| `docker compose -f docker-compose.prod.yml exec backend php artisan reservations:expire` | Force purge expired inventory holds (runs every 1 min) |
| `docker compose -f docker-compose.prod.yml exec backend php artisan payments:reconcile` | Force reconciliation of pending Paystack payments (runs every 5 min) |
| `docker compose -f docker-compose.prod.yml exec backend php artisan queue:restart` | Gracefully restart background queue workers |
| `docker compose -f docker-compose.prod.yml exec backend php artisan config:cache` | Cache production configuration |
| `docker compose -f docker-compose.prod.yml exec backend php artisan route:cache` | Cache API routing table |
