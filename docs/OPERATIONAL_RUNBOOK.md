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

## 2. Deployment Procedures

### 2.1 Zero-Downtime Deployment Steps

1. **Pull and Build Containers**:
   ```bash
   docker compose -f docker-compose.prod.yml pull
   docker compose -f docker-compose.prod.yml build --pull
   ```

2. **Execute Database Migrations** (Backwards-compatible only):
   ```bash
   docker compose -f docker-compose.prod.yml exec backend php artisan migrate --force
   ```

3. **Reload Application Cache**:
   ```bash
   docker compose -f docker-compose.prod.yml exec backend php artisan config:cache
   docker compose -f docker-compose.prod.yml exec backend php artisan route:cache
   docker compose -f docker-compose.prod.yml exec backend php artisan view:cache
   ```

4. **Graceful Worker & Server Reload**:
   ```bash
   docker compose -f docker-compose.prod.yml exec backend php artisan queue:restart
   docker compose -f docker-compose.prod.yml restart nginx
   ```

5. **Verify Health**:
   ```bash
   curl -f https://ticket.clevawave.com/api/v1/health
   ```

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
    docker compose exec backend php artisan payments:reconcile
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
  docker compose exec backend grep "Invalid Paystack webhook signature" /var/log/nginx/error.log
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
docker compose exec backend /var/www/html/infra/scripts/backup.sh
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
   - Run `php artisan config:cache`.
2. **Admin Password / Credential Reset**:
   - Super admins can manage or disable accounts at `/admin/users`.
   - Force password re-hash using Argon2id.
3. **Application Encryption Key (`APP_KEY`)**:
   - If rotating `APP_KEY`, re-encrypt existing MFA TOTP secrets using `Crypt::encryptString`.

---

## 6. CLI Administration Cheat Sheet

| Command | Purpose |
|---|---|
| `php artisan admin:create-super` | Provision a new Super Administrator account interactively |
| `php artisan reservations:expire` | Force purge expired inventory holds (runs every 1 min) |
| `php artisan payments:reconcile` | Force reconciliation of pending Paystack payments (runs every 5 min) |
| `php artisan queue:work --tries=3` | Run the background job worker |
| `php artisan config:cache` | Cache production configuration |
| `php artisan route:cache` | Cache API routing table |
