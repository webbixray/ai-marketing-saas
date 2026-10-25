#!/bin/bash
# =============================================
# Zero-Downtime Deployment Script
# AI Marketing SaaS — Production
# =============================================

set -euo pipefail

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log() { echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $*"; }
warn() { echo -e "${YELLOW}[$(date +'%Y-%m-%d %H:%M:%S')] WARNING:${NC} $*"; }
error() { echo -e "${RED}[$(date +'%Y-%m-%d %H:%M:%S')] ERROR:${NC} $*"; exit 1; }
info() { echo -e "${BLUE}[$(date +'%Y-%m-%d %H:%M:%S')] INFO:${NC} $*"; }

# Configuration
APP_DIR="/opt/ai-marketing-saas"
COMPOSE_FILES="-f docker-compose.yml -f docker-compose.prod.yml"
IMAGE="${REGISTRY:-ghcr.io}/${IMAGE_NAME:-ai-marketing-saas}"
TAG="${TAG:-latest}"
HEALTH_CHECK_URL="${HEALTH_CHECK_URL:-https://localhost/up}"
MAX_RETRIES=30
RETRY_INTERVAL=10
ROLLBACK_ON_FAILURE="${ROLLBACK_ON_FAILURE:-true}"

# Backup current state for rollback
BACKUP_DIR="${APP_DIR}/backups/$(date +%Y%m%d_%H%M%S)"

usage() {
    cat << EOF
Usage: $0 [OPTIONS]

Options:
  -t, --tag TAG         Docker image tag to deploy (default: latest)
  -i, --image IMAGE     Docker image name (default: ghcr.io/ai-marketing-saas)
  -e, --env-file FILE   Environment file (default: .env)
  -r, --no-rollback     Disable automatic rollback on failure
  -h, --help            Show this help

Environment Variables:
  REGISTRY              Container registry (default: ghcr.io)
  IMAGE_NAME            Image name (default: ai-marketing-saas)
  TAG                   Image tag (default: latest)
  HEALTH_CHECK_URL      Health check endpoint (default: https://localhost/up)
  ROLLBACK_ON_FAILURE   Enable rollback (default: true)

Examples:
  $0                          # Deploy latest
  $0 -t v1.2.3                # Deploy specific version
  $0 --no-rollback            # Deploy without rollback
EOF
    exit 1
}

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        -t|--tag) TAG="$2"; shift 2 ;;
        -i|--image) IMAGE="$2"; shift 2 ;;
        -e|--env-file) ENV_FILE="$2"; shift 2 ;;
        -r|--no-rollback) ROLLBACK_ON_FAILURE="false"; shift ;;
        -h|--help) usage ;;
        *) error "Unknown option: $1" ;;
    esac
done

# ---------------------------------------------
# Pre-deployment checks
# ---------------------------------------------
log "=== AI Marketing SaaS Deployment Started ==="
info "Image: ${IMAGE}:${TAG}"
info "App directory: ${APP_DIR}"
info "Rollback on failure: ${ROLLBACK_ON_FAILURE}"

# Check if running as appuser or root
if [[ $EUID -eq 0 ]]; then
    warn "Running as root — switching to appuser for docker commands"
    RUN_AS_APPUSER="sudo -u appuser"
else
    RUN_AS_APPUSER=""
fi

cd "${APP_DIR}" || error "App directory not found: ${APP_DIR}"

# Check environment file
ENV_FILE="${ENV_FILE:-.env}"
[[ -f "${ENV_FILE}" ]] || error "Environment file not found: ${ENV_FILE}"

# Verify docker compose
${RUN_AS_APPUSER} docker compose ${COMPOSE_FILES} config >/dev/null || error "Docker compose configuration invalid"

# ---------------------------------------------
# Backup current state
# ---------------------------------------------
log "Creating backup for potential rollback..."
mkdir -p "${BACKUP_DIR}"

# Backup docker images currently running
${RUN_AS_APPUSER} docker compose ${COMPOSE_FILES} ps --format "table {{.Service}}\t{{.Image}}" > "${BACKUP_DIR}/running_images.txt" 2>/dev/null || true

# Backup database
log "Backing up database..."
${RUN_AS_APPUSER} docker compose ${COMPOSE_FILES} exec -T db pg_dump -U "${DB_USERNAME}" "${DB_DATABASE}" | gzip > "${BACKUP_DIR}/database.sql.gz" || warn "Database backup failed"

# Backup .env
cp "${ENV_FILE}" "${BACKUP_DIR}/.env.backup"

log "Backup saved to: ${BACKUP_DIR}"

# ---------------------------------------------
# Pull new image
# ---------------------------------------------
log "Pulling new image: ${IMAGE}:${TAG}..."
${RUN_AS_APPUSER} docker compose ${COMPOSE_FILES} pull app || error "Failed to pull image"

# ---------------------------------------------
# Deploy with zero-downtime
# ---------------------------------------------
log "Starting zero-downtime deployment..."

# Start new container alongside old one
${RUN_AS_APPUSER} docker compose ${COMPOSE_FILES} up -d --no-deps --scale app=2 app || error "Failed to start new container"

# Wait for new container to be healthy
log "Waiting for new container to pass health checks..."
RETRY=0
while [[ ${RETRY} -lt ${MAX_RETRIES} ]]; do
    if ${RUN_AS_APPUSER} docker compose ${COMPOSE_FILES} ps app --format "table {{.Status}}" | grep -q "healthy"; then
        log "New container is healthy!"
        break
    fi
    RETRY=$((RETRY + 1))
    info "Health check attempt ${RETRY}/${MAX_RETRIES}..."
    sleep ${RETRY_INTERVAL}
done

if [[ ${RETRY} -eq ${MAX_RETRIES} ]]; then
    error "New container failed health checks after ${MAX_RETRIES} attempts"
fi

# Run migrations on new container
log "Running database migrations..."
${RUN_AS_APPUSER} docker compose ${COMPOSE_FILES} exec -T app php artisan migrate --force || {
    warn "Migrations failed — initiating rollback"
    rollback
    exit 1
}

# Clear and rebuild caches
log "Optimizing application..."
${RUN_AS_APPUSER} docker compose ${COMPOSE_FILES} exec -T app php artisan config:cache
${RUN_AS_APPUSER} docker compose ${COMPOSE_FILES} exec -T app php artisan route:cache
${RUN_AS_APPUSER} docker compose ${COMPOSE_FILES} exec -T app php artisan view:cache
${RUN_AS_APPUSER} docker compose ${COMPOSE_FILES} exec -T app php artisan event:cache

# Verify application responds
log "Verifying application response..."
if curl -f -s -o /dev/null --max-time 30 "${HEALTH_CHECK_URL}"; then
    log "Application health check passed!"
else
    error "Application health check failed"
fi

# Stop old container (scale back to 1)
log "Removing old container..."
${RUN_AS_APPUSER} docker compose ${COMPOSE_FILES} up -d --no-deps --scale app=1 app || error "Failed to scale down"

# Final verification
sleep 5
if curl -f -s -o /dev/null --max-time 30 "${HEALTH_CHECK_URL}"; then
    log "✅ Deployment successful!"
else
    error "Post-deployment health check failed"
fi

# ---------------------------------------------
# Cleanup
# ---------------------------------------------
log "Cleaning up old images..."
${RUN_AS_APPUSER} docker image prune -f --filter "until=24h" || true

# Keep only last 5 backups
log "Pruning old backups (keeping last 5)..."
ls -dt "${APP_DIR}/backups/"* 2>/dev/null | tail -n +6 | xargs rm -rf || true

log "=== Deployment Complete ==="
info "Version: ${TAG}"
info "Backup: ${BACKUP_DIR}"

# ---------------------------------------------
# Rollback function
# ---------------------------------------------
rollback() {
    warn "=== INITIATING ROLLBACK ==="

    # Restore database
    if [[ -f "${BACKUP_DIR}/database.sql.gz" ]]; then
        log "Restoring database..."
        gunzip -c "${BACKUP_DIR}/database.sql.gz" | ${RUN_AS_APPUSER} docker compose ${COMPOSE_FILES} exec -T db psql -U "${DB_USERNAME}" -d "${DB_DATABASE}" || warn "Database restore failed"
    fi

    # Restore .env
    if [[ -f "${BACKUP_DIR}/.env.backup" ]]; then
        cp "${BACKUP_DIR}/.env.backup" "${ENV_FILE}"
    fi

    # Restore previous containers
    log "Restoring previous containers..."
    ${RUN_AS_APPUSER} docker compose ${COMPOSE_FILES} down
    ${RUN_AS_APPUSER} docker compose ${COMPOSE_FILES} up -d

    warn "Rollback complete. Check application status."
}