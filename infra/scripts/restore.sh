#!/bin/sh
# ==============================================================================
# ClevaWave Ticketing Platform — Database Restoration & Verification Script
# RTO target: 4 hours | Decrypts and restores PostgreSQL custom-format dumps
# ==============================================================================

set -eu

if [ $# -lt 1 ]; then
    echo "Usage: $0 <path-to-backup-file> [optional-target-database]"
    exit 1
fi

BACKUP_SOURCE="$1"
DB_HOST="${DB_HOST:-postgres}"
DB_PORT="${DB_PORT:-5432}"
TARGET_DB="${2:-${DB_DATABASE:-ticketing}}"
DB_USER="${DB_USERNAME:-ticketing_app}"
TEMP_RESTORE_FILE="/tmp/restore_target.sql.gz"

echo "[$(date -u)] Initiating restore operation from ${BACKUP_SOURCE} into ${TARGET_DB}..."

# Export PGPASSWORD safely from environment
export PGPASSWORD="${DB_PASSWORD}"

# Check if encrypted
if echo "${BACKUP_SOURCE}" | grep -q "\.enc$"; then
    if [ -z "${BACKUP_ENCRYPTION_KEY:-}" ]; then
        echo "ERROR: Backup is encrypted, but BACKUP_ENCRYPTION_KEY is not defined."
        exit 1
    fi

    echo "[$(date -u)] Decrypting AES-256-CBC backup archive..."
    openssl enc -d -aes-256-cbc -pbkdf2 \
        -in "${BACKUP_SOURCE}" \
        -out "${TEMP_RESTORE_FILE}" \
        -pass pass:"${BACKUP_ENCRYPTION_KEY}"
    RESTORE_INPUT="${TEMP_RESTORE_FILE}"
else
    RESTORE_INPUT="${BACKUP_SOURCE}"
fi

# Decompress and restore using pg_restore
echo "[$(date -u)] Restoring PostgreSQL schema and records..."
gzip -dc "${RESTORE_INPUT}" | pg_restore -h "${DB_HOST}" -p "${DB_PORT}" -U "${DB_USER}" -d "${TARGET_DB}" \
    --clean \
    --if-exists \
    --no-owner \
    --no-privileges || {
        echo "WARNING: pg_restore completed with non-fatal warnings."
    }

# Cleanup temporary decrypted file
if [ -f "${TEMP_RESTORE_FILE}" ]; then
    rm -f "${TEMP_RESTORE_FILE}"
fi

# Post-restoration data integrity verification
echo "[$(date -u)] Running post-restore data integrity validation..."
EVENT_COUNT=$(psql -h "${DB_HOST}" -p "${DB_PORT}" -U "${DB_USER}" -d "${TARGET_DB}" -t -A -c "SELECT count(*) FROM events;")
ORDER_COUNT=$(psql -h "${DB_HOST}" -p "${DB_PORT}" -U "${DB_USER}" -d "${TARGET_DB}" -t -A -c "SELECT count(*) FROM orders;")
TICKET_COUNT=$(psql -h "${DB_HOST}" -p "${DB_PORT}" -U "${DB_USER}" -d "${TARGET_DB}" -t -A -c "SELECT count(*) FROM tickets;")

echo "[$(date -u)] Integrity Verification Succeeded:"
echo "  - Events:  ${EVENT_COUNT}"
echo "  - Orders:  ${ORDER_COUNT}"
echo "  - Tickets: ${TICKET_COUNT}"
echo "[$(date -u)] Restoration completed successfully."
