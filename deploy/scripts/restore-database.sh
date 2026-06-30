#!/usr/bin/env bash
set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
readonly SCRIPT_DIR
DEPLOY_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
readonly DEPLOY_ROOT
readonly APP_ENV_FILE="${APP_ENV_FILE:-${DEPLOY_ROOT}/app.env}"
readonly BACKUP_SCRIPT="${SCRIPT_DIR}/backup-database.sh"

# shellcheck disable=SC2034 # consumed by deploy-common.sh log() and fail()
DEPLOY_LOG_PREFIX="restore"

# shellcheck source=deploy/scripts/lib/deploy-common.sh
source "${SCRIPT_DIR}/lib/deploy-common.sh"

BACKUP_PATH="${1:-}"
SAFETY_BACKUP=""
restore_failed=0

# shellcheck disable=SC2329 # invoked via trap EXIT
cleanup_on_failure() {
    if [[ "${restore_failed}" -eq 1 ]]; then
        log "Restore failed. Application may still be in maintenance mode."
        log "A safety backup was created before restore — restore it manually if needed:"
        if [[ -n "${SAFETY_BACKUP}" ]]; then
            log "  ${SAFETY_BACKUP}"
        fi
        exit 1
    fi
}

trap cleanup_on_failure EXIT

if [[ -z "${BACKUP_PATH}" ]]; then
    fail "Usage: $0 /path/to/dentalfinance-YYYYMMDDTHHMMSSZ.dump"
fi

if [[ ! -f "${BACKUP_PATH}" ]]; then
    fail "Backup file not found: ${BACKUP_PATH}"
fi

if ! docker run --rm -i postgres:16-bookworm pg_restore --list < "${BACKUP_PATH}" >/dev/null 2>&1; then
    fail "Backup integrity check failed."
fi

if [[ ! -f "${APP_ENV_FILE}" ]]; then
    fail "Missing ${APP_ENV_FILE}"
fi

resolve_app_image
export APP_IMAGE

if [[ "$(read_env_value APP_ENV || echo production)" != "production" ]]; then
    log "Warning: APP_ENV is not production."
fi

printf '\n*** PRODUCTION DATABASE RESTORE ***\n'
printf 'This will REPLACE the current PostgreSQL database.\n'
printf 'Backup: %s\n\n' "${BACKUP_PATH}"
read -r -p "Type RESTORE to continue: " confirmation
if [[ "${confirmation}" != "RESTORE" ]]; then
    fail "Restore cancelled."
fi

log "Creating safety backup before restore..."
"${BACKUP_SCRIPT}"
SAFETY_BACKUP="$(
    find "${DEPLOY_ROOT}/backups/database" -maxdepth 1 -type f -name 'dentalfinance-*.dump' -printf '%T@ %p\n' 2>/dev/null \
        | sort -rn \
        | head -n1 \
        | cut -d' ' -f2- \
        || true
)"

log "Enabling maintenance mode..."
compose exec -T app php artisan down --retry=60 || true

log "Stopping worker and scheduler..."
compose stop worker scheduler || true

log "Terminating active database connections..."
terminate_database_connections || true

log "Restoring database..."
# shellcheck disable=SC2016 # POSTGRES_USER and POSTGRES_DB must expand inside the database container
if ! compose exec -T database sh -c \
    'pg_restore -U "$POSTGRES_USER" -d "$POSTGRES_DB" --clean --if-exists --no-owner --no-privileges --exit-on-error --single-transaction' \
    < "${BACKUP_PATH}"; then
    restore_failed=1
    fail "Database restore failed."
fi

log "Starting application containers..."
compose up -d app worker scheduler

log "Disabling maintenance mode..."
compose exec -T app php artisan up

log "Running internal healthcheck..."
if ! wait_for_internal_health 12 10; then
    restore_failed=1
    fail "Internal healthcheck failed after restore."
fi

trap - EXIT
log "Restore completed successfully."

exit 0
