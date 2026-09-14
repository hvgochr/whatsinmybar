# Abuse protection

The public launch baseline uses Symfony RateLimiter and Lock, a local filesystem
volume and one short `flock` critical section. No Redis, CAPTCHA or database
migration is needed for this single-VPS application.

## Default budgets

| Operation | Identity | Attempts / window | Environment variables |
| --- | --- | --- | --- |
| Login | Client IP | 40 / 15 minutes | `ABUSE_LOGIN_IP_LIMIT`, `ABUSE_LOGIN_INTERVAL` |
| Login | Client IP + normalized email | 8 / 15 minutes | `ABUSE_LOGIN_ACCOUNT_LIMIT`, `ABUSE_LOGIN_INTERVAL` |
| Registration | Client IP | 5 / hour | `ABUSE_REGISTRATION_LIMIT`, `ABUSE_REGISTRATION_INTERVAL` |
| All authenticated API writes | User ID | 120 / 10 minutes | `ABUSE_WRITES_LIMIT`, `ABUSE_WRITES_INTERVAL` |
| Comments and replies | User ID | 20 / 10 minutes | `ABUSE_COMMENTS_LIMIT`, `ABUSE_COMMENTS_INTERVAL` |
| Reports | User ID | 5 / hour | `ABUSE_REPORTS_LIMIT`, `ABUSE_REPORTS_INTERVAL` |
| Recipe writes | User ID | 60 / 10 minutes | `ABUSE_RECIPES_LIMIT`, `ABUSE_RECIPES_INTERVAL` |
| Avatar and recipe image writes | User ID | 10 / 10 minutes | `ABUSE_UPLOADS_LIMIT`, `ABUSE_UPLOADS_INTERVAL` |

Limits count attempts, including malformed/invalid bodies and successful writes.
Login is checked before password verification. Email is lowercased and trimmed
for the quota only; authentication behavior does not change. Both successful
and failed logins count, and success does not reset the quota. An account is
not globally locked across IPs: this avoids remotely locking out its owner.
The IP budget is spent even when the account/IP budget rejects a login.

Authenticated quotas use the server-authenticated user ID, never an input user
ID, token string or IP. Renewing a JWT, changing IP, changing the resource or
using another endpoint cannot reset these budgets. API Platform format suffixes
(`.json`, `.jsonld`) and encoded paths are classified by matched route/resource.
Administrators also count.
The overall write budget covers profile/password changes, favorites and admin
mutations, alongside the more specific budgets. The recipe budget includes
aggregate create/update, API Platform create/patch/delete, steps, measured
ingredients, publication/archive, favorites and admin recipe edits. Comments
include create/reply/edit/delete; uploads include both image deletion and upload.
Reports submitted to `/api/reports` have their own budget; admin report handling
uses the general write budget. A rejected category quota does not spend the
general write quota. Existing authorization and upload validation still apply.

Budgets use fixed windows starting with the first attempt. Once a window expires,
its capacity returns; rejected attempts do not extend it. This is simple and
predictable but permits up to twice the quota near a window boundary. The chosen
limits leave room for editor saves and compatibility child operations while
constraining repeated submissions. Shared public IPs also share registration
and login-IP capacity. IPv6 addresses are individual keys, not /64 groups.

## Responses and session restoration

All quota rejections return `429` and a positive integer `Retry-After` in seconds:

```json
{"error":{"status":429,"code":"too_many_requests","message":"Too many requests. Please try again later."}}
```

The delay covers the longest exhausted budget, rounded conservatively. The
frontend displays a wait message in English and does not automatically replay
writes. CORS exposes `Retry-After`; errors retain private/no-store cache headers.

GET/HEAD/OPTIONS and authentication refresh/logout do not consume these quotas.
SSR discovery, `/me`, parallel tabs and session rotation therefore cannot exhaust
a write or login budget. Existing refresh deadlines, deduplication, transient
failure backoff and cookie rotation remain in effect. These exclusions are
deliberate; this is not a universal request limiter.

## Storage and maintenance

Counters live in `/app/var/abuse/prod/counters` in the production `api_abuse`
volume; development uses `/app/var/abuse/dev` in `api_var`. The namespace is stable
across builds, and neither `cache:clear` nor framework cache-pool clearing resets
it. Restarts and container replacement preserve unexpired quotas. Keep the
volume when updating the application, with write permission for `www-data`.
Changing `APP_SECRET`, deleting the volume or deliberately deleting its counters
resets quotas. Keys are HMAC-SHA256 identifiers using `APP_SECRET`; raw IPs and
emails are not stored by the limiter. Existing access-log retention is separate.

Symfony filesystem cache operations and one shared local lock serialize counter
updates across PHP workers/processes. Only counter IO happens while locked.
There is one lock file, so arbitrary emails cannot create unbounded lock files.
Failure to save a counter returns `503` with `Retry-After: 60` instead of accepting
unmetered work. Monitor disk space, permissions and application errors.

Expired entries are logically ignored immediately. Run this daily from the VPS
scheduler to reclaim their disk space without resetting active quotas:

```bash
docker compose --env-file .env.production --env-file .env.deploy -f compose.prod.yaml exec -T api php bin/console app:abuse:prune
```

Limits and intervals default in `apps/api/config/packages/abuse.yaml`. Both
Compose files explicitly pass all listed variables. Add overrides to `.env.production`
(or the root development `.env`) and recreate the API container to apply them.
Use positive integer capacities and positive PHP relative intervals such as
`15 minutes`. Lowering a limit can take effect against an existing counter;
an interval change is fully effective once old windows expire. Do not clear
storage as an ordinary deployment step.

## Proxy trust boundary

Internet must reach the separately managed shared Caddy first. The production
Compose publishes no ports. Symfony trusts only the configured exact
`CADDY_PROXY_IP` and Nuxt's dedicated `WEB_INTERNAL_IP` (default `172.30.72.3`), and only
`X-Forwarded-For` and `X-Forwarded-Proto`. There is no `REMOTE_ADDR` wildcard,
private-range trust, or trust in `Forwarded`, `X-Real-IP` or forwarded host headers.
Caddy overwrites `X-Forwarded-For` with the TCP peer IP and strips the alternative
IP headers on both API and Nuxt upstreams. No CDN/load balancer is assumed.

Nuxt checks its socket peer against the private `NUXT_TRUSTED_PROXY_IP` setting
before accepting Caddy's single forwarded IP. Direct Nuxt callers cannot forge
it. The verified address is forwarded per SSR request to Symfony, alongside the
existing restricted cookie transport; it is not stored in global state. Direct
API callers outside the trusted proxy addresses cannot override their IP.

Development uses `172.30.71.0/24`; the dedicated production SSR network uses
`172.30.72.0/24` by default. Dynamic containers allocate from `.128/25`, reserving
Nuxt's static address. Caddy remains on the existing external `proxy` network:
inspect its actual IP rather than assuming one from this subnet. SSR targets the
API alias on the dedicated network. See [Production deployment](docker-prod.md)
for exact settings, shared-proxy address changes and overlap checks. Never attach untrusted
containers to this network or expose API/Nuxt ports publicly. Adding a CDN,
another proxy, replicas or another VPS requires revisiting this trust and storage
design. A compromised trusted application container is outside this boundary.

## Validation and limits

`make check` covers quota rejection, account normalization, distinct IPs/users,
equivalent routes, session/read exclusions, expiration, process recreation,
concurrent consumption, forwarded-header isolation and frontend messages.
PHPUnit uses a per-process test directory and clears it between tests, while
preserving quotas across kernel reboots within each test. `make check-containers`
tests both Caddy configurations with forged headers against local echo upstreams
inside a network-disabled disposable image. It also builds production images.
No production load testing is involved.

These application quotas do not stop distributed attacks across many IPs/accounts,
slow connections, bandwidth floods, or uploads before PHP has received their
body. Existing upload size/type limits remain required. Fixed-window bursts and
NAT sharing are accepted tradeoffs for a low-traffic portfolio. Observe actual
429 frequency and resource usage before tightening limits or adding edge-level
defenses, CAPTCHA, shared storage or account-wide risk controls.
