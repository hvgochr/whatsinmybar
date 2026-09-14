# Production on the shared OVH VPS

WhatsInMyBar uses two GHCR images, PostgreSQL 16 and local persistent uploads.
The existing Caddy in /srv/proxy terminates HTTPS. This repository does not start
another Caddy or publish any host port. GameSentry stays independently managed.

## Files and prerequisites

- `compose.prod.yaml`: API, Nuxt, PostgreSQL, log rotation and persistent volumes.
- `infra/caddy/Caddyfile.prod`: site block to merge into the existing Caddyfile.
- `.github/workflows/images.yml`: builds both images from one main commit and
  produces a `release.env` artifact containing their immutable digests.
- `infra/ops/deploy.sh`: serialized manual deployment with HTTP checks.
- `infra/ops/backup.sh`: consistent database/uploads backup and off-site copy.
- `infra/ops/systemd/`: optional backup, pruning, disk/freshness checks and alerts.
- `infra/ops/compose.restore.yaml`: disposable restoration environment.

On the VPS, use /srv/whatsinmybar with Docker Compose v2 supporting
`up --wait` and `start --wait`, Bash, flock (util-linux), curl, rsync, OpenSSH,
gzip and coreutils. No Python, Redis, S3 or deployment framework is required.
The backup script briefly stops **only WhatsInMyBar API/web**. Allow this small
nightly outage for a consistent database/upload pair.

No workflow deploys or connects to the VPS. Publication runs only after a push
to main; opening a PR only builds/tests local runner images. Both builds must
succeed before a release artifact exists. Keep the artifact and its digests:
SHA tags identify the source, but deployment pins the actual image digests.
Require application/container checks before merging into main. GHCR access for
the VPS is a separate read-only package credential; do not commit it.

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
cp .env.prod.example .env.prod
chmod 600 .env.prod
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

## Deploy and inspect

Use the operational files from the same commit as the downloaded release
artifact. For updates, retain the previous compose.prod.yaml and operational
files before replacing them. Copy/configure these files while holding
`.ops.lock`, with timers stopped during configuration maintenance.
Keep .env.prod and .env.deploy outside any source synchronization/deletion.

From /srv/whatsinmybar:

```bash
bash infra/ops/deploy.sh /absolute/path/to/release.env
```

The script takes the shared lock, strictly parses the digest manifest, pulls
images, waits for PostgreSQL, migrates with the selected API image, recreates
API/web and checks their health and image references. Public requests check
Caddy /healthz, Nuxt /robots.txt and the DB-backed API categories endpoint.
During a first deployment, install/reload the Caddy block once containers are
ready; if public checks fail before that, reload Caddy then rerun the script.

The internal API check requests /api/categories?page=1: it exercises PHP,
Symfony and PostgreSQL. Nuxt's /robots.txt proves the built HTTP server serves
requests; it does not prove API dependencies or authenticated SSR. /healthz
alone proves only the edge. Monitor the API and frontend separately.

Only verified success replaces .env.deploy; the prior manifest becomes
.env.deploy.previous. .env.deploy.pending records a deployment that has started
mutating the stack. Failure leaves it for diagnosis and blocks scheduled
backups; do not blindly delete it. A failed migration may already have changed
the schema. Container replacement or public-check failure is not automatically
rolled back.

Routine commands use both files:

```bash
compose() {
  docker compose --env-file .env.prod --env-file .env.deploy -f compose.prod.yaml "$@"
}
compose ps
compose logs --tail=100 api web postgres
compose exec -T api php bin/console doctrine:migrations:status
```

Avoid exported API_IMAGE/WEB_IMAGE in the operator shell: exports override env
files. During failed deployment diagnosis, explicitly use the pending manifest
if inspecting the attempted release.

## Rollback and persistence

For an application rollback, first verify that the current database schema is
compatible with the old code. Copy the previous release manifest to a separate
file, restore the matching Compose/operational files under the maintenance lock,
then run `bash infra/ops/deploy.sh /path/to/saved-release.env --skip-migrations`.
This explicit mode never asks old migration code to change the current schema. For a failed update, .env.deploy
still identifies the last verified release; after a successful update use
.env.deploy.previous. Deploying an old manifest does not undo migrations.
If migrations are incompatible, plan a maintenance window and coordinated
database/upload restore; expect loss of writes after the backup.
Do not run down migrations automatically.

The Compose project remains **whatsinmybar-prod**. It preserves:
`whatsinmybar-prod_postgres_data`, `whatsinmybar-prod_api_uploads` and
`whatsinmybar-prod_api_abuse`. Do not change the project name or use down -v.
API upload and abuse mounts remain /app/public/uploads and /app/var/abuse;
the image still initializes them for www-data. No volume data migration is
needed for an existing deployment using this project name.

If a previous installation used another project name, inspect its container
mounts first and explicitly map the three existing volumes before starting
anything. An empty new database is not a successful migration. If an old
standalone WhatsInMyBar Caddy exists, retire that identified container using
its old Compose configuration without deleting application volumes. The shared
Caddy is never managed by this Compose file.

Ordinary deploy, restart, cache:clear and daily app:abuse:prune preserve active
quotas. Backups intentionally do not snapshot quota counters: do not restore
old counters over live ones. Disaster recovery to a fresh host starts fresh
quotas. Preserve the live abuse volume during an in-place data recovery.

## Backups, schedules and alerts

Choose a dedicated off-site SSH destination first. It must exist, be writable
by the backup account and provide encryption at rest. Verify its SSH host key
and install a restricted key for the systemd execution user (`hugo` in the templates).
Use no interactive password prompt and no StrictHostKeyChecking=no.
The template refuses to run until BACKUP_REMOTE is set.

```bash
sudo install -d -m 700 /etc/whatsinmybar
sudo install -m 600 infra/ops/backup.env.example /etc/whatsinmybar/backup.env
sudo install -d -o hugo -g hugo -m 700 /srv/backups/whatsinmybar
```

Adjust User/Group in the units if the deployment account is not hugo. Use the
same account for manual operations; it must own /srv/whatsinmybar, belong to the
Docker group and have write access to the backup directory. systemd loads the
root-owned EnvironmentFile before changing users.

Edit backup.env: local directory, remote destination and retention (7 days
locally proposed). backup.sh locks against deployment/pruning, checks that all
services are healthy, stops API/web, dumps PostgreSQL with strict pipeline
failure handling, archives uploads including ownership, and restarts API/web.
A trap attempts restart on failure. A SIGKILL/host crash can still leave
services stopped: monitor availability and inspect compose ps after recovery.

It verifies compressed files/checksums, sends the complete timestamped directory
with rsync over SSH, then applies local retention. Failed dumps produce no
completed bundle; failed remote copies retain the local bundle and skip
retention. Retry a failed transfer of that directory and verify SHA256SUMS at
the destination. Each bundle contains database.sql.gz, uploads.tar.gz, the image
manifest and Compose file, not .env.prod. Back up secrets separately in encrypted
storage so disaster recovery can recover them.

The remote destination must enforce its own retention; rsync never uses
--delete. Proposed starting policy: 7 days locally, 30 days off-site, daily
backups, monthly restore drill. Confirm storage cost, accepted nightly downtime
and a maximum 24-hour data-loss window before installation. Remote snapshots
or an append-only account reduce damage if the VPS credentials are compromised.

Create /etc/whatsinmybar/alert.curl, mode 600, containing a curl `url = "..."`
setting for a provisioned HTTPS webhook accepting a form field named unit.
The template sends the failed systemd unit name, no application secrets.
Adapt this tiny alert unit to an existing notification provider if needed.
Test the alert endpoint and verify delivery before relying on timers.

After a successful manual backup and restore drill, installation is explicit:

```bash
sudo install -m 644 infra/ops/systemd/* /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl start whatsinmybar-backup.service
sudo systemctl start whatsinmybar-abuse-prune.service
sudo systemctl start whatsinmybar-check-backups.service
sudo systemctl enable --now whatsinmybar-backup.timer whatsinmybar-abuse-prune.timer whatsinmybar-check-backups.timer
```

Backup runs around 04:00 UTC, pruning around 04:30 UTC; these differ from the
existing GameSentry backup at 03:30 UTC. Pruning invokes the existing Symfony
command as the API user and logs to the journal. OnFailure calls the alert unit.
An hourly check reports disks at 85% and missing successful off-site copies
for 36 hours; adjust the checked Docker data path if the daemon uses a custom
data-root. Also configure an **external** HTTPS monitor for the frontend and
/api/categories?page=1, with alerts and a small nightly maintenance window.
A dead VPS cannot send local failure alerts.

Application logs rotate at 10 MiB x 3 per container. Verify shared Caddy/Docker
and systemd-journal retention separately; this repository does not edit the
proxy project or global journald settings.

```bash
systemctl list-timers 'whatsinmybar-*'
journalctl -u whatsinmybar-backup -u whatsinmybar-abuse-prune -u whatsinmybar-check-backups --since yesterday
```

## Restore drill: isolated database and uploads

Use a trusted backup on a separate machine or an isolated test environment,
never the production Compose. Generate a new, unique project name for every
drill. The restore file has no public ports, no proxy and no production mounts.

Run in Bash; set BACKUP to the downloaded bundle's absolute path:

```bash
set -euo pipefail
export RESTORE_PASSWORD
RESTORE_PASSWORD=$(openssl rand -hex 32)
RESTORE_PROJECT="wimb-restore-$(date -u +%Y%m%d%H%M%S)"
(cd "$BACKUP" && sha256sum -c SHA256SUMS)
restore() {
  docker compose -p "$RESTORE_PROJECT" --env-file "$BACKUP/release.env" \
    -f infra/ops/compose.restore.yaml "$@"
}
restore up -d --wait postgres
gzip -dc "$BACKUP/database.sql.gz" \
  | restore exec -T postgres psql -U restore -d restore --set ON_ERROR_STOP=on
restore run --rm --no-deps -T uploads -C /restore -xzf - < "$BACKUP/uploads.tar.gz"
restore exec -T postgres psql -U restore -d restore -c '\dt'
restore run --rm --no-deps -T uploads -C /restore -tzf - < "$BACKUP/uploads.tar.gz"
```

Verify representative users/recipes and image references in the restored
database, inspect restored files/ownership and compare counts with the backup
source. The archive listing alone does not verify application behavior.
Before accepting recovery, boot the matching application release in a separately
configured test stack and check login/refresh, SSR, avatar rendering and protected
recipe images for anonymous, minor and adult users. No production proxy/volumes
may be attached. Then remove only the drill's volumes:

```bash
restore down -v
unset RESTORE_PASSWORD
```

## Validation coverage

- `make check-ops`: Bash syntax, ShellCheck, fake-command failure injection
  (pg_dump failure restarts services; failed rsync retains the local backup;
  pending deployment blocks backup).
- `make check-containers`: Compose interpolation, both production builds,
  isolated upload rules and forged-header tests using both actual Caddyfiles.
- Container CI also starts a disposable production stack with a simulated
  shared proxy. It verifies separate visitor IP budgets, rejected spoofed headers,
  direct untrusted API callers, quotas after container/cache/prune operations,
  DB-backed HTTP health and an SSR page request.
- Backend/frontend CI rerun for production infrastructure changes and preserve
  the existing quota, IP, per-request SSR forwarding, session and upload tests.

The CI stack changes only its public listener to HTTP. It cannot validate the
real VPS network, ACME, private GHCR pulls, off-site credentials, notification
delivery, restore drill or real authenticated browser acceptance.
Do not report these as passed based only on isolated tests.
