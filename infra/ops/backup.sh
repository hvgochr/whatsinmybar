#!/usr/bin/env bash
# Run from /srv/whatsinmybar. Settings come from systemd EnvironmentFile.
set -euo pipefail
umask 077

: "${BACKUP_DIR:?Set an absolute backup directory}"
: "${BACKUP_REMOTE:?Set user@host:/absolute/backup/path}"
RETENTION_DAYS=${RETENTION_DAYS:-7}
[[ "$BACKUP_DIR" == /* && "$BACKUP_REMOTE" =~ ^[^[:space:]:]+@[^[:space:]:]+:/[^[:space:]]+$ ]]
[[ "$RETENTION_DAYS" =~ ^[1-9][0-9]*$ ]]
[[ -f .env.deploy && ! -e .env.deploy.pending ]]
exec 9>.ops.lock
flock -w 900 9
# Recheck after obtaining the deployment/maintenance lock.
[[ -f .env.deploy && ! -e .env.deploy.pending ]]
unset API_IMAGE WEB_IMAGE
compose() { docker compose --env-file .env.prod --env-file .env.deploy -f compose.prod.yaml "$@"; }

mkdir -p -- "$BACKUP_DIR"
temporary=$(mktemp -d "$BACKUP_DIR/.partial-XXXXXXXX")
resume=0
cleanup() {
  status=$?
  trap - EXIT
  if (( resume )); then
    compose start --wait --wait-timeout 180 api web || status=1
  fi
  rm -rf -- "$temporary"
  if (( status != 0 )); then echo "Backup failed; inspect the journal and application health." >&2; fi
  exit "$status"
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

# Refuse to turn a deliberately stopped stack back on.
for service in api web postgres; do
  container=$(compose ps -q "$service")
  [[ -n "$container" && "$(docker inspect --format '{{.State.Health.Status}}' "$container")" == healthy ]]
done

# A short write outage gives PostgreSQL and uploads the same consistency point.
# No other operator/importer may write while holding this lock.
resume=1
compose stop -t 60 api web
compose exec -T postgres sh -c 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" --no-owner --no-acl' \
  | gzip > "$temporary/database.sql.gz"
compose run --rm --no-deps -T --user root --entrypoint tar api \
  -C /app/public/uploads -czf - . > "$temporary/uploads.tar.gz"
cp -- .env.deploy "$temporary/release.env"
cp -- compose.prod.yaml "$temporary/compose.prod.yaml"
compose start --wait --wait-timeout 180 api web
resume=0

(
  cd -- "$temporary"
  gzip -t database.sql.gz
  tar -tzf uploads.tar.gz > /dev/null
  sha256sum database.sql.gz uploads.tar.gz release.env compose.prod.yaml > SHA256SUMS
)
destination="$BACKUP_DIR/backup-$(date -u +%Y%m%dT%H%M%SZ)"
[[ ! -e "$destination" ]]
mv -- "$temporary" "$destination"

# Dedicated SSH destination; no --delete. Failed transfers retain all local copies.
rsync -a --protect-args --timeout=120 -e 'ssh -o BatchMode=yes -o ConnectTimeout=15' \
  -- "$destination" "$BACKUP_REMOTE/"
find "$BACKUP_DIR" -mindepth 1 -maxdepth 1 -type d -name 'backup-*' \
  -mtime "+$RETENTION_DAYS" ! -path "$destination" -exec rm -rf -- {} +
touch -- "$BACKUP_DIR/last-success"
echo "Backup verified and copied off-site: $destination"
