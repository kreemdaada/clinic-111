#!/usr/bin/env bash
set -Eeuo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
readonly SCRIPT_DIR
DEPLOY_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
readonly DEPLOY_ROOT
readonly APP_ENV_FILE="${APP_ENV_FILE:-${DEPLOY_ROOT}/app.env}"
readonly BACKUP_DIR="${BACKUP_DIR:-${DEPLOY_ROOT}/backups/database}"
readonly RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-14}"

# shellcheck disable=SC2034 # consumed by deploy-common.sh log() and fail()
DEPLOY_LOG_PREFIX="backup"

# shellcheck source=deploy/scripts/lib/deploy-common.sh
source "${SCRIPT_DIR}/lib/deploy-common.sh"

mkdir -p "${BACKUP_DIR}"

if [[ ! -f "${APP_ENV_FILE}" ]]; then
    fail "Missing ${APP_ENV_FILE}"
fi

resolve_app_image
export APP_IMAGE

timestamp="$(date -u +"%Y%m%dT%H%M%SZ")"
tmp_file="${BACKUP_DIR}/.dentalfinance-${timestamp}.dump.tmp"
final_file="${BACKUP_DIR}/dentalfinance-${timestamp}.dump"

log "Creating PostgreSQL custom-format backup..."

# shellcheck disable=SC2016 # POSTGRES_USER and POSTGRES_DB must expand inside the database container
compose exec -T database sh -c 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Fc --no-owner --no-privileges' > "${tmp_file}"

if [[ ! -s "${tmp_file}" ]]; then
    rm -f "${tmp_file}"
    fail "Backup file is empty."
fi

if ! docker run --rm -i postgres:16-bookworm pg_restore --list < "${tmp_file}" >/dev/null 2>&1; then
    rm -f "${tmp_file}"
    fail "Backup integrity check failed (pg_restore --list)."
fi

mv "${tmp_file}" "${final_file}"
log "Backup written: ${final_file}"

find "${BACKUP_DIR}" -type f -name 'dentalfinance-*.dump' -mtime +"${RETENTION_DAYS}" -delete 2>/dev/null || true
log "Retention applied (${RETENTION_DAYS} days)."

exit 0
