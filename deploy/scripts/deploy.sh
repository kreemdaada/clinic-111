#!/usr/bin/env bash
set -Eeuo pipefail

readonly SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
readonly DEPLOY_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
readonly APP_ENV_FILE="${APP_ENV_FILE:-${DEPLOY_ROOT}/app.env}"
readonly BACKUP_SCRIPT="${SCRIPT_DIR}/backup-database.sh"

DEPLOY_LOG_PREFIX="deploy"

# shellcheck source=lib/deploy-common.sh
source "${SCRIPT_DIR}/lib/deploy-common.sh"

IMAGE_TAG="${1:-}"
HEALTH_RETRIES="${HEALTH_RETRIES:-12}"
HEALTH_INTERVAL="${HEALTH_INTERVAL:-10}"

cleanup_lock() {
    rm -f "${LOCK_FILE}"
}

on_error() {
    local exit_code=$?
    if [[ -f "${LOCK_FILE}" ]]; then
        log "Deployment failed with exit code ${exit_code}."
    fi
    exit "${exit_code}"
}

trap on_error ERR

if [[ -z "${IMAGE_TAG}" ]]; then
    fail "Usage: $0 <full-git-commit-sha>"
fi

if [[ ! "${IMAGE_TAG}" =~ ^[0-9a-fA-F]{40}$ ]]; then
    fail "IMAGE_TAG must be a full 40-character Git commit SHA."
fi

require_command docker
require_command flock

if [[ ! -f "${APP_ENV_FILE}" ]]; then
    fail "Missing ${APP_ENV_FILE} — copy deploy/app.env.example and configure secrets."
fi

exec 9>"${LOCK_FILE}"
if ! flock -n 9; then
    fail "Another deployment is already running (lock: ${LOCK_FILE})."
fi

trap cleanup_lock EXIT

cd "${DEPLOY_ROOT}"

NEW_IMAGE="$(build_image_from_tag "${IMAGE_TAG}")"

PREVIOUS_IMAGE=""
if [[ -f "${STATE_FILE}" ]]; then
    PREVIOUS_IMAGE="$(grep -E '^APP_IMAGE=' "${STATE_FILE}" | tail -n1 | cut -d= -f2- || true)"
fi

log "Starting deployment."
log "New image: ${NEW_IMAGE}"
if [[ -n "${PREVIOUS_IMAGE}" ]]; then
    log "Previous image: ${PREVIOUS_IMAGE}"
fi

export APP_IMAGE="${NEW_IMAGE}"

log "Pulling image..."
docker pull "${APP_IMAGE}"

wait_for_database

log "Creating pre-migration backup..."
"${BACKUP_SCRIPT}"

log "Running migrations..."
compose run --rm --no-deps --entrypoint php app artisan migrate --force --no-interaction

log "Starting application containers..."
compose up -d --remove-orphans

log "Optimizing Laravel..."
compose exec -T app php artisan optimize --no-interaction

log "Waiting for internal Laravel healthcheck at ${INTERNAL_HEALTH_URL}..."
if ! wait_for_internal_health "${HEALTH_RETRIES}" "${HEALTH_INTERVAL}"; then
    if [[ -n "${PREVIOUS_IMAGE}" ]]; then
        log "Healthcheck failed — rolling back application to previous image: ${PREVIOUS_IMAGE}"
        export APP_IMAGE="${PREVIOUS_IMAGE}"
        compose up -d --remove-orphans

        if wait_for_internal_health "${HEALTH_RETRIES}" "${HEALTH_INTERVAL}"; then
            fail "Deployment failed. Rollback to ${PREVIOUS_IMAGE} succeeded. Database was NOT rolled back — review migrations manually."
        fi

        fail "Deployment failed. Rollback to ${PREVIOUS_IMAGE} also failed — manual intervention required. Database was NOT rolled back."
    fi

    fail "Healthcheck failed and no previous image is recorded in ${STATE_FILE}."
fi

printf 'APP_IMAGE=%s\n' "${NEW_IMAGE}" > "${STATE_FILE}"
printf 'DEPLOYED_AT=%s\n' "$(date -u +"%Y-%m-%dT%H:%M:%SZ")" >> "${STATE_FILE}"

log "Deployment successful."
log "Active image: ${NEW_IMAGE}"
log "Note: rolling back the application image does not reverse database migrations."

exit 0
