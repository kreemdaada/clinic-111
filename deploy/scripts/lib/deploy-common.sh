#!/usr/bin/env bash
# Shared helpers for DentalFinance production deployment scripts.
# Do not source app.env — use docker compose --env-file instead.

# shellcheck disable=SC2034 # sourced-library marker for consumer scripts
readonly DEPLOY_COMMON_LOADED=1

: "${DEPLOY_ROOT:?DEPLOY_ROOT must be set before sourcing deploy-common.sh}"
: "${APP_ENV_FILE:?APP_ENV_FILE must be set before sourcing deploy-common.sh}"

readonly COMPOSE_FILE="${DEPLOY_ROOT}/compose.production.yml"
readonly STATE_FILE="${DEPLOY_ROOT}/.deploy-state"
# shellcheck disable=SC2034 # used by deploy.sh after sourcing this library
readonly LOCK_FILE="${DEPLOY_ROOT}/.deploy.lock"
readonly INTERNAL_HEALTH_URL="http://127.0.0.1:8080/up"

compose() {
    docker compose \
        --env-file "${APP_ENV_FILE}" \
        -f "${COMPOSE_FILE}" \
        "$@"
}

log() {
    printf '[%s] %s\n' "${DEPLOY_LOG_PREFIX:-deploy}" "$*"
}

fail() {
    printf '[%s] ERROR: %s\n' "${DEPLOY_LOG_PREFIX:-deploy}" "$*" >&2
    exit 1
}

require_command() {
    command -v "$1" >/dev/null 2>&1 || fail "Required command not found: $1"
}

read_env_value() {
    local key="$1"
    local line

    line="$(grep -E "^${key}=" "${APP_ENV_FILE}" | tail -n1 || true)"
    if [[ -z "${line}" ]]; then
        return 1
    fi

    printf '%s' "${line#*=}"
}

resolve_app_image() {
    if [[ -n "${APP_IMAGE:-}" ]]; then
        export APP_IMAGE
        return 0
    fi

    if [[ -f "${STATE_FILE}" ]]; then
        APP_IMAGE="$(grep -E '^APP_IMAGE=' "${STATE_FILE}" | tail -n1 | cut -d= -f2- || true)"
    fi

    if [[ -z "${APP_IMAGE:-}" ]]; then
        fail "APP_IMAGE is not set and ${STATE_FILE} does not contain a deployed image. Run deploy.sh for the first deployment."
    fi

    export APP_IMAGE
}

wait_for_database() {
    local attempt

    compose up -d database

    for attempt in $(seq 1 30); do
        if compose exec -T database pg_isready -U "$(read_env_value DB_USERNAME || echo dentalfinance)" \
            -d "$(read_env_value DB_DATABASE || echo dentalfinance)" >/dev/null 2>&1; then
            return 0
        fi
        sleep 2
    done

    fail "Database is not ready."
}

internal_healthcheck() {
    compose exec -T app curl -fsS "${INTERNAL_HEALTH_URL}" >/dev/null
}

wait_for_internal_health() {
    local retries="${1:-12}"
    local interval="${2:-10}"
    local attempt

    for attempt in $(seq 1 "${retries}"); do
        if internal_healthcheck; then
            return 0
        fi
        log "Internal health attempt ${attempt}/${retries} failed — retrying in ${interval}s..."
        sleep "${interval}"
    done

    return 1
}

build_image_from_tag() {
    local image_tag="$1"
    local ghcr_image

    ghcr_image="$(read_env_value GHCR_IMAGE || true)"
    if [[ -z "${ghcr_image}" ]]; then
        ghcr_image="ghcr.io/kreemdaada/clinic-111"
    fi

    printf '%s:%s' "${ghcr_image}" "${image_tag}"
}

terminate_database_connections() {
    compose exec -T database psql \
        -U "$(read_env_value DB_USERNAME || echo dentalfinance)" \
        -d "$(read_env_value DB_DATABASE || echo dentalfinance)" \
        -v ON_ERROR_STOP=1 \
        -c "SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = current_database() AND pid <> pg_backend_pid();" \
        >/dev/null
}

container_exists() {
    docker container inspect "$1" >/dev/null 2>&1
}

container_is_running() {
    [[ "$(docker inspect -f '{{.State.Running}}' "$1" 2>/dev/null || echo false)" == "true" ]]
}

stop_compose_service_if_present() {
    local service="$1"
    local stop_timeout="$2"
    local container_name="dentalfinance_${service}"

    if ! container_exists "${container_name}"; then
        log "Container ${container_name} not found — skipping ${service} stop."
        return 0
    fi

    if ! container_is_running "${container_name}"; then
        log "Container ${container_name} already stopped."
        return 0
    fi

    log "Stopping ${service} (grace ${stop_timeout}s)..."
    compose stop -t "${stop_timeout}" "${service}"
}

stop_background_services_for_deploy() {
    local app_container="dentalfinance_app"

    if ! container_is_running "${app_container}"; then
        log "App container not running — skipping queue:restart and worker/scheduler stop."
        return 0
    fi

    log "Signalling queue workers to restart after the current job..."
    compose exec -T app php artisan queue:restart

    log "Waiting up to 10s for workers to observe queue:restart..."
    sleep 10

    stop_compose_service_if_present worker 120
    stop_compose_service_if_present scheduler 30
}

compose_up_application() {
    compose up -d \
        --timeout 30 \
        --wait \
        --wait-timeout 180 \
        --remove-orphans
}
