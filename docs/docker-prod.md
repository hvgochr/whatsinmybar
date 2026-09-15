# Production on the shared OVH VPS

The deployment follows GameSentry: GitHub Actions builds the images and deploys
over SSH; host backups, Docker cleanup and timers are managed on the VPS.
No operational scripts or systemd files are installed from the repository.

## Layout and prerequisites

In /srv/whatsinmybar:
- compose.prod.yaml: uploaded from the published commit;
- .env.production: provisioned on the VPS, never replaced by Actions;
- .env.deploy: last verified API_IMAGE and WEB_IMAGE digests;
- .env.deploy.previous and compose.prod.yaml.previous: previous verified pair.

Keep the Compose project name **whatsinmybar-prod**. This preserves existing
postgres_data, api_uploads and api_abuse volume identities. Do not rename it to
match GameSentry's shorter naming, or use down -v. The uploads and abuse mounts
remain /app/public/uploads and /app/var/abuse, writable by www-data.
Existing installs using another project name must explicitly map their three
existing volumes before starting the stack.

If you created .env.prod during the earlier PR draft, rename it to
.env.production and update any host commands that reference it. No database or
upload migration is required. Old standalone WhatsInMyBar Caddy installations
must retire that identified container with their old configuration without
deleting data; the shared Caddy is never controlled by this Compose.

Use Docker Compose v2 supporting up/start --wait, Bash, flock, curl, OpenSSH,
gzip and coreutils. Backup copies also need rsync when enabled.

## Shared network and exact proxy trust


Inspect the actual VPS before filling in production settings:

```bash
docker network inspect proxy
docker inspect CADDY_CONTAINER --format '{{json .NetworkSettings.Networks}}'
docker network ls
```

Record the proxy network's subnet, allocation pool, IPv6 setting, Caddy's IPv4
and existing attachments. Confirm that the dedicated application subnet
(default 172.30.72.0/24) does not overlap Docker, host or VPN routes.
Do not recreate proxy, move Caddy, or change GameSentry's networking.

Set `CADDY_PROXY_IP` to Caddy's exact IPv4 on proxy. It deliberately has no
production default. Confirm the Caddy connection actually uses that IPv4;
if proxy is dual-stack, verify address selection before launch. The current
Nuxt trust setting accepts one address, not a CIDR or a comma-separated list.
If Caddy is recreated with a different address, re-inspect it, update this
setting and recreate WhatsInMyBar API/web. Do not broaden trust to private
ranges or all containers on proxy to avoid maintaining this setting.
A stable Caddy address can be planned in the separately managed proxy project;
this PR does not alter that network.

| Connection | Destination | Trusted source |
| --- | --- | --- |
| Caddy to API | whatsinmybar-api:8080 on proxy | CADDY_PROXY_IP |
| Caddy to Nuxt | whatsinmybar-web:3000 on proxy | CADDY_PROXY_IP |
| Nuxt SSR to API | whatsinmybar-api-internal:8080 on app | WEB_INTERNAL_IP |
| API to PostgreSQL | postgres:5432 on database | Private database network |

Only API/Nuxt join proxy. The internal app network gives Nuxt its own static
address (default 172.30.72.3); SSR uses an alias that exists **only** on app,
so its source is deterministic without reserving an IP on the shared network.
If needed, change APP_SUBNET, APP_IP_RANGE and WEB_INTERNAL_IP together.
The dynamic pool must exclude the static Nuxt address.

Symfony trusts only the exact Caddy address and WEB_INTERNAL_IP. Caddy replaces
X-Forwarded-For with the TCP peer address and strips Forwarded/X-Real-IP on both
upstreams. Nuxt checks its socket peer and forwards the verified client IP per
SSR request. Other proxy-network containers are not trusted IP forwarders.
The site assumes direct Internet-to-Caddy traffic, without a CDN/load balancer.

## Production settings and Caddy

Create the secret file and replace **all** example placeholders:

```bash
cp .env.production.example .env.production
chmod 600 .env.production
openssl rand -hex 32
openssl rand -hex 32
openssl rand -hex 32
```

Use independent generated values for APP_SECRET, JWT_SECRET and
POSTGRES_PASSWORD. Keep DATABASE_URL consistent (URL-encode the password).
Keep APP_DOMAIN and anchored CORS_ALLOW_ORIGIN on the real HTTPS origin.
The refresh cookie remains HttpOnly, Secure, SameSite=Strict and Path=/;
the browser uses /api on the same origin. Never rotate APP_SECRET as part of
ordinary deployment: it also namespaces the abuse counters.

In /srv/proxy, back up its Caddyfile and integrate the **site block** from
infra/caddy/Caddyfile.prod alongside GameSentry. Do not replace the whole shared
configuration. Validate the complete file with the existing Caddy container,
then reload it using that project's normal procedure after API/web are ready.
Review the exact container and mounted path before running either command.

The block routes /api and /uploads to the API. FrankenPHP still denies direct
recipe image files and hidden upload files; canonical avatars remain public.
Protected /api/recipe-images/* responses retain private/no-store and the recipe
voter. Do not add a static upload alias, image optimizer or shared caching.
No API image routing configuration or authorization is loosened by this change.

## GitHub Actions: validation, publication and deployment

On every push to main, docker.yml calls the existing backend.yml, frontend.yml
and containers.yml through workflow_call. Local workflow references and checkout
use the caller's exact commit. The publish job needs all three CI jobs to succeed
before building and publishing the API and web SHA tags. The CI workflows retain
their path-filtered pull_request triggers; their main push triggers are replaced
by these calls, so main validation runs once and cannot be bypassed by path filters.
Workflow changes also exercise the reusable CI graph on pull requests, with
publication skipped.

deploy.yml listens for successful completion of docker.yml, checks out that exact
commit and uploads only Compose. No SSH deployment runs for a pull request.

Configure the production GitHub environment with:
- secrets DEPLOY_SSH_KEY and DEPLOY_KNOWN_HOSTS (verified host key);
- variables DEPLOY_HOST and DEPLOY_USER.

Provision /srv/whatsinmybar and .env.production for that user, allow it to use
Docker, and authenticate it to GHCR with read-only package access. Use the same
user for host backup/prune jobs so .ops.lock has consistent ownership.
Set DNS, integrate the shared Caddy site block and prepare host backups before
merging/enabling the first production workflow. Main pushes will then deploy
automatically; this PR does not perform that setup or a deployment.

The workflow uses GitHub's production concurrency group plus a host flock.
After acquiring GitHub's concurrency slot and immediately before the first scp,
it compares the published SHA with the current main SHA through the GitHub API.
An obsolete release skips both scp and SSH; an API failure stops the workflow.
Thus an older build finishing after a newer release cannot overwrite it. A new
push after this check does not interrupt the running deployment; its validated
release can deploy afterwards through the same concurrency group.
Compose is staged under a unique name and only replaced under the lock. The
workflow pulls SHA-tagged images, resolves their actual digests, preserves the
previous Compose/image pair, starts PostgreSQL without recreating it, migrates
using the selected API image, and recreates API/web. It verifies image references,
container health, public Caddy /healthz, Nuxt /robots.txt and DB-backed
/api/categories?page=1 before atomically recording .env.deploy.

API health exercises Symfony and PostgreSQL through a real HTTP request.
Nuxt's check proves its built HTTP server responds; it does not prove authenticated
SSR. /healthz alone proves only Caddy. Keep the existing session/browser tests.

.env.deploy.pending marks an attempted deployment. Failures preserve it and stop
later deployments/backups rather than overwrite recovery evidence. There is no
automatic database rollback. Resolve the failure using the following procedures.

## Manual commands and recovery

From /srv/whatsinmybar:

```bash
compose() {
  docker compose --env-file .env.production --env-file .env.deploy \
    -p whatsinmybar-prod -f compose.prod.yaml "$@"
}
compose ps
compose logs --tail=100 api web postgres
compose exec -T api php bin/console doctrine:migrations:status
```

After the first successful migration, bootstrap the first administrator as a
separate, one-time operation. Inject a strong secret from the operator's secret
manager or a temporary environment variable; never add it to
`.env.production`, Compose defaults, shell history or the development seed:

```bash
read -rs APP_ADMIN_PASSWORD
export APP_ADMIN_PASSWORD
compose exec -T -e APP_ADMIN_PASSWORD api php bin/console app:admin:bootstrap \
  --email=owner@example.com --username=owner --birth-date=1990-01-01
unset APP_ADMIN_PASSWORD
```

Rerunning the exact command is safe: an existing active administrator is left
unchanged, including its password. Identifier collisions and deleted matching
accounts fail closed and require manual review.

For a failed first deployment, fix the cause and use .env.deploy.pending as the
env file for manual diagnosis. Once understood, archive/remove that pending file
under .ops.lock and rerun the failed deploy workflow. Never label it successful
just by renaming the file.

For rollback, verify schema compatibility with the old code first. Under the
same lock, restore compose.prod.yaml.previous and .env.deploy.previous, pull the
recorded digests, then recreate API/web and repeat health/public checks:
```bash
# Run in a dedicated Bash subshell after checking the recovery pair.
(
  set -euo pipefail
  exec 9>.ops.lock
  flock -w 900 9
  unset API_IMAGE WEB_IMAGE
  cp compose.prod.yaml.previous compose.prod.yaml
  cp .env.deploy.previous .env.deploy
  cp .env.deploy.previous .env.deploy.pending
  docker compose --env-file .env.production --env-file .env.deploy \
    -p whatsinmybar-prod -f compose.prod.yaml pull api web
  docker compose --env-file .env.production --env-file .env.deploy \
    -p whatsinmybar-prod -f compose.prod.yaml up -d --no-deps --force-recreate --wait api web
  for path in /healthz /robots.txt '/api/categories?page=1'; do
    curl --fail --silent --show-error --max-time 15 --output /dev/null \
      "https://whatsinmybar.charradehugo.com$path"
  done
  rm -f .env.deploy.pending
)
```

Do not run old migrations during an application rollback. Incompatible schema
changes require a planned coordinated database/uploads restore and can lose
writes since the backup. On a first deployment there is no previous pair.

## Host backup: /usr/local/bin/backup-whatsinmybar

Install and schedule this on the VPS like backup-gamesentry, not in this repo.
Use the same deployment user, chmod 750, and /srv/backups/whatsinmybar with mode
700. It uses pg_dump custom format and pg_restore --list like GameSentry.
Uploads need a matching archive, so it briefly stops only WhatsInMyBar API/web
while creating the pair. All maintenance/importers must respect .ops.lock.

```bash
#!/usr/bin/env bash
set -euo pipefail
umask 077
APP_DIR="/srv/whatsinmybar"
BACKUP_DIR="/srv/backups/whatsinmybar"
TIMESTAMP="$(date -u +%Y-%m-%d_%H-%M-%S)"
cd "$APP_DIR"
exec 9>.ops.lock
flock -w 900 9
[[ -f .env.deploy && ! -e .env.deploy.pending ]]
unset API_IMAGE WEB_IMAGE
compose() {
  docker compose --env-file .env.production --env-file .env.deploy \
    -p whatsinmybar-prod -f compose.prod.yaml "$@"
}
mkdir -p "$BACKUP_DIR"
PARTIAL="$(mktemp -d "$BACKUP_DIR/.partial-XXXXXXXX")"
RESUME=0
cleanup() {
  STATUS=$?
  trap - EXIT
  if (( RESUME )); then compose start --wait api web || STATUS=1; fi
  rm -rf -- "$PARTIAL"
  exit "$STATUS"
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM
for SERVICE in api web postgres; do
  CONTAINER="$(compose ps -q "$SERVICE")"
  [[ -n "$CONTAINER" && "$(docker inspect --format '{{.State.Health.Status}}' "$CONTAINER")" == healthy ]]
done
RESUME=1
compose stop -t 60 api web
# Variables expand inside PostgreSQL.
compose exec -T postgres sh -c 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" -Fc' > "$PARTIAL/database.dump"
compose exec -T postgres pg_restore --list < "$PARTIAL/database.dump" > /dev/null
compose run --rm --no-deps -T --user root --entrypoint tar api \
  -C /app/public/uploads -czf - . > "$PARTIAL/uploads.tar.gz"
cp .env.deploy "$PARTIAL/release.env"
cp compose.prod.yaml "$PARTIAL/compose.prod.yaml"
compose start --wait api web
RESUME=0
(
  cd "$PARTIAL"
  tar -tzf uploads.tar.gz > /dev/null
  sha256sum database.dump uploads.tar.gz release.env compose.prod.yaml > SHA256SUMS
)
BACKUP="$BACKUP_DIR/whatsinmybar-$TIMESTAMP"
[[ ! -e "$BACKUP" ]]
mv "$PARTIAL" "$BACKUP"
# Optional user@host:/absolute/path from the host job environment.
# When configured, a failed transfer stops retention and preserves local copies.
if [[ -n "${BACKUP_REMOTE:-}" ]]; then
  rsync -a --protect-args --timeout=120 -e 'ssh -o BatchMode=yes -o ConnectTimeout=15' \
    -- "$BACKUP" "$BACKUP_REMOTE/"
fi
find "$BACKUP_DIR" -mindepth 1 -maxdepth 1 -type d -name 'whatsinmybar-*' \
  -mtime +7 ! -path "$BACKUP" -exec rm -rf -- {} +
echo "Backup complete: $BACKUP"
```

Without BACKUP_REMOTE this only provides **local** backups, like the current
GameSentry script. Before launch choose an encrypted off-site destination,
verify its SSH host key/access, and configure BACKUP_REMOTE on the host job.
No rsync --delete is used. Enforce remote retention at the destination
(proposed: 7 days local, 30 days off-site) and verify transferred SHA256SUMS.
Keep secrets (.env.production) separately backed up in encrypted storage.
The backup does not overwrite or snapshot active abuse counters.

## Host scheduling, cleanup and monitoring

Reuse the existing systemd/cron approach on the VPS. Suggested backup time:
04:00 UTC, separate from GameSentry's 03:30 UTC. Configure the backup service
to execute /usr/local/bin/backup-whatsinmybar, with WorkingDirectory set to
/srv/whatsinmybar and the deployment user. Use its EnvironmentFile for
BACKUP_REMOTE if needed. Give stop/start enough time and alert on failure.

Schedule this existing Symfony command daily (e.g. 04:30 UTC), with the same
working directory and user:

```bash
flock -w 900 .ops.lock docker compose \
  --env-file .env.production --env-file .env.deploy \
  -p whatsinmybar-prod -f compose.prod.yaml \
  exec -T api php bin/console app:abuse:prune
```

This prunes expired counters while preserving active quotas and their lock.
Container recreation and cache:clear also retain the api_abuse volume.
Changing APP_SECRET or deleting that volume resets quotas.

Keep the existing /usr/local/bin/docker-cleanup: image/build-cache pruning
already covers all Docker projects. Do not add another cleanup job or volume
pruning. Cached rollback images older than seven days may be removed when no
container uses them; keep GHCR releases available so recorded digests can be
pulled again.

Application logs rotate at 10 MiB x 3 per container. Keep shared Caddy and
journald retention in the host configuration. Add host disk alerts around 85%,
an off-site backup freshness alert (36 hours), and external HTTP monitoring of
the frontend and /api/categories?page=1. The host jobs should log to the journal
and use your notification provider on failure. Verify alert delivery, actual
backup restoration and the nightly downtime window before relying on them.
No timers or server scripts are installed or activated by this PR.

## Isolated restore drill

Download a trusted complete bundle to a test machine. Set BACKUP to its absolute
path. Use a fresh container and volume name each time; never the production
Compose or volumes. This example publishes no ports and uses no network.

```bash
set -euo pipefail
RESTORE="wimb-restore-$(date -u +%Y%m%d%H%M%S)"
export RESTORE_PASSWORD
RESTORE_PASSWORD="$(openssl rand -hex 32)"
(cd "$BACKUP" && sha256sum -c SHA256SUMS)
docker run -d --name "$RESTORE" --network none \
  -e POSTGRES_USER=restore -e POSTGRES_DB=restore \
  -e POSTGRES_PASSWORD="$RESTORE_PASSWORD" \
  -v "$RESTORE-db:/var/lib/postgresql/data" \
  --health-cmd 'pg_isready -U restore -d restore' \
  --health-interval 2s postgres:16-alpine
# Wait until docker inspect reports healthy before the restore:
docker inspect --format '{{.State.Health.Status}}' "$RESTORE"
docker exec -i "$RESTORE" pg_restore -U restore -d restore \
  --no-owner --no-acl --exit-on-error < "$BACKUP/database.dump"
docker run --rm -i --network none -v "$RESTORE-uploads:/restore" \
  postgres:16-alpine tar -C /restore -xzf - < "$BACKUP/uploads.tar.gz"
docker exec "$RESTORE" psql -U restore -d restore -c '\dt'
docker run --rm --network none -v "$RESTORE-uploads:/restore:ro" \
  postgres:16-alpine find /restore -type f
```

Compare representative recipes and image references with restored files.
A successful pg_restore --list only checks the archive catalog, not restorability.
Before accepting recovery, check login/refresh, SSR and protected images with
the matching release in a separately configured test stack. Dispose of the
drill only after verification:

```bash
docker rm -f "$RESTORE"
docker volume rm "$RESTORE-db" "$RESTORE-uploads"
unset RESTORE_PASSWORD
```

## Validation

Container CI checks workflow syntax/embedded Bash, Compose, both image builds,
actual upload/proxy routing and a disposable production stack. The latter tests
distinct IP budgets, forged headers, direct untrusted API callers, counter
persistence after recreation/cache/pruning and an SSR page request.
The smoke scenario is inline in containers.yml, not an operational script.
Backend/frontend CI preserve existing authorization, session and SSR tests.

CI does not connect to the VPS or execute the SSH deployment or host backup.
Real network addressing, ACME, GHCR credentials, backups/restoration and
notification delivery still require the host acceptance steps above.
