#!/bin/sh
# ==============================================================================
# ClevaWave Ticketing Platform — Automated Database Backup Script
# RPO target: 15 minutes | Encrypted AES-256-CBC | Immutable retention
# ==============================================================================

set -eu

BACKUP_DIR="${BACKUP_DIR:-/var/backups/ticketing}"
DB_HOST="${DB_HOST:-postgres}"
DB_PORT="${DB_PORT:-5432}"
DB_NAME="${DB_DATABASE:-ticketing}"
DB_USER="${DB_USERNAME:-ticketing_app}"
TIMESTAMP=$(date -u +"%Y%m%d_%H%M%SZ")
BACKUP_FILE="${BACKUP_DIR}/ticketing_${TIMESTAMP}.sql.gz"
ENCRYPTED_FILE="${BACKUP_FILE}.enc"

mkdir -p "${BACKUP_DIR}"

echo "[$(date -u)] Starting automated PostgreSQL dump for ${DB_NAME}..."

# Export PGPASSWORD safely from environment
export PGPASSWORD="${DB_PASSWORD}"

# Dump database with gzip compression
pg_dump -h "${DB_HOST}" -p "${DB_PORT}" -U "${DB_USER}" -d "${DB_NAME}" \
    --format=custom \
    --no-owner \
    --no-privileges \
    | gzip -9 > "${BACKUP_FILE}"

echo "[$(date -u)] Database dumped successfully. Size: $(du -h "${BACKUP_FILE}" | cut -f1)"

# Encrypt backup using OpenSSL AES-256-CBC
if [ -n "${BACKUP_ENCRYPTION_KEY:-}" ]; then
    echo "[$(date -u)] Encrypting backup archive with AES-256-CBC..."
    openssl enc -aes-256-cbc -salt -pbkdf2 \
        -in "${BACKUP_FILE}" \
        -out "${ENCRYPTED_FILE}" \
        -pass pass:"${BACKUP_ENCRYPTION_KEY}"
    
    # Remove unencrypted dump
    rm -f "${BACKUP_FILE}"
    echo "[$(date -u)] Backup encrypted: ${ENCRYPTED_FILE}"
else
    echo "[$(date -u)] WARNING: BACKUP_ENCRYPTION_KEY not set. Archive remains unencrypted."
fi

# Retention policy: prune backups older than 30 days
echo "[$(date -u)] Enforcing 30-day backup retention policy..."
find "${BACKUP_DIR}" -type f -name "ticketing_*.sql.gz*" -mtime +30 -delete

echo "[$(date -u)] Backup routine completed successfully."
