#!/usr/bin/env bash
# Run from the repository root: bash infra/ops/deploy.sh /path/to/release.env
set -euo pipefail
umask 077

release_file=$(realpath -- "${1:?Usage: deploy.sh /path/to/release.env}")
mode=${2:-deploy}
[[ "$mode" == deploy || "$mode" == --skip-migrations ]]
[[ -f .env.prod && -f compose.prod.yaml ]]
exec 9>.ops.lock
flock -w 900 9

# Parse the three CI-generated values, never execute an environment file.
unset API_IMAGE WEB_IMAGE WIMB_RELEASE
while IFS='=' read -r key value || [[ -n "$key" ]]; do
  case "$key" in
    API_IMAGE)
      [[ -z "${API_IMAGE:-}" && "$value" =~ ^ghcr[.]io/hvgochr/whatsinmybar-api@sha256:[a-f0-9]{64}$ ]]
      export API_IMAGE="$value" ;;
    WEB_IMAGE)
      [[ -z "${WEB_IMAGE:-}" && "$value" =~ ^ghcr[.]io/hvgochr/whatsinmybar-web@sha256:[a-f0-9]{64}$ ]]
      export WEB_IMAGE="$value" ;;
    WIMB_RELEASE)
      [[ -z "${WIMB_RELEASE:-}" && "$value" =~ ^[a-f0-9]{40}$ ]]
      export WIMB_RELEASE="$value" ;;
    *) echo "Invalid release file." >&2; exit 1 ;;
  esac
done < "$release_file"
: "${API_IMAGE:?Missing API_IMAGE}" "${WEB_IMAGE:?Missing WEB_IMAGE}" "${WIMB_RELEASE:?Missing WIMB_RELEASE}"

compose() { docker compose --env-file .env.prod -f compose.prod.yaml "$@"; }
trap 'echo "Deployment failed; inspect containers and .env.deploy.pending before recovery." >&2; compose ps -a' ERR

compose config --quiet
compose pull api web postgres
printf 'WIMB_RELEASE=%s\nAPI_IMAGE=%s\nWEB_IMAGE=%s\n' \
  "$WIMB_RELEASE" "$API_IMAGE" "$WEB_IMAGE" > .env.deploy.pending

# Keep PostgreSQL running and migrate with the selected API image.
compose up -d --no-recreate --wait --wait-timeout 120 postgres
if [[ "$mode" == deploy ]]; then
  compose run --rm --no-deps -T api php bin/console doctrine:migrations:migrate --no-interaction
fi
compose up -d --no-deps --force-recreate --wait --wait-timeout 180 api web

for service in api web; do
  container=$(compose ps -q "$service")
  expected="$API_IMAGE"
  [[ "$service" != web ]] || expected="$WEB_IMAGE"
  [[ "$(docker inspect --format '{{.Config.Image}}' "$container")" == "$expected" ]]
  [[ "$(docker inspect --format '{{.State.Health.Status}}' "$container")" == healthy ]]
done

# Use the hostname of the actual API container, not a separately supplied URL.
domain=$(compose exec -T api printenv DEFAULT_URI)
for path in /healthz /robots.txt '/api/categories?page=1'; do
  curl --fail --silent --show-error --output /dev/null \
    --connect-timeout 5 --max-time 15 --retry 5 --retry-delay 2 "$domain$path"
done

if [[ -f .env.deploy ]]; then
  cp -- .env.deploy .env.deploy.previous.tmp
  mv -- .env.deploy.previous.tmp .env.deploy.previous
fi
mv -- .env.deploy.pending .env.deploy
echo "Verified release: $WIMB_RELEASE"
