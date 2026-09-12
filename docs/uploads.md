# Local image storage

The single-VPS deployment deliberately uses local storage with the persistent
`api_uploads` volume mounted at `/app/public/uploads`. S3 is not required.
Back up this volume together with PostgreSQL; losing either can leave missing
images or unreferenced files. No schema migration or object move is needed.

## Validation and transformations

Multipart avatars (`avatar`) are limited to 2 MiB and recipe images (`image`)
to 5 MiB. PHP transport limits remain 5 MiB per file and 8 MiB per request.
Incomplete uploads, empty files, unsupported formats and invalid images return
400; transport rejection by a proxy/PHP can happen before the controller.

Only JPEG, PNG and WebP are accepted, regardless of the client filename or
Content-Type. Headers are inspected for dimensions before allocating decoded
pixels: each side must be at most 4096 pixels and the total at most 8,000,000
pixels. GD must then successfully decode the image without decoder warnings.
Header recognition alone is never considered validation.

The decoded pixels are copied to a fresh true-color image, proportionally
shrunk to fit 512 pixels for avatars or 1600 pixels for recipes, without
upscaling. The original format is preserved: JPEG/WebP quality 85, PNG
compression 6 with transparency. Only the reencoded image is published. EXIF,
GPS, IPTC, color profiles and appended data are not copied. Animated input is
not preserved: formats that GD cannot decode are rejected; otherwise only a
static image is produced. EXIF orientation is not applied, so images should be
visually oriented before upload. The encoded file must also fit the byte limit.

PHP's `ext-gd` is the only added runtime dependency; it supplies the actual
JPEG/PNG/WebP decoders, resizing and encoders. Docker's shared API base and the
backend CI install it. Composer explicitly requires it. The Composer-only
vendor build stage ignores this one extension check; the final API image has
GD. No Composer package versions are upgraded.

Names are generated from 128 random bits. Encoding writes a hidden temporary
file in the destination directory, then renames it atomically. A failed encode
removes its temporary file; process interruption can leave one for maintenance.
Pre-existing uploaded bytes are not retroactively reencoded. Replace existing
images through the upload endpoints to apply the new validation policy.

## Replacement and deletion

Avatar replacement and recipe image replacement/removal use a dedicated outer
Doctrine transaction. The owner row is refreshed under a pessimistic write lock
to serialize concurrent replacements; recipe management authorization is
rechecked after refreshing. Deleted owners cannot receive an image.

The old file remains until the database commit succeeds. Only an unreferenced
file is removed. On persistence failure, the new image is removed if the
database confirms it is unreferenced. If commit acknowledgement or the database
connection is lost, retain the file rather than risk deleting committed data;
log the deferred cleanup and reconcile later. Filesystem cleanup errors do not
hide the original persistence error or turn a committed replacement into a
failed response.

Soft deletion and moderation retain the database reference and file for possible
restoration. Removing the recipe image explicitly clears the reference and
removes the unreferenced file after commit. There is no new avatar-delete API.

## Delivery and confidentiality

Before this change, both FrankenPHP configurations used `php_server` on the
public directory. Caddy forwarded `/uploads/*` to it. Consequently anyone who
knew a recipe image URL could retrieve its bytes directly, including after
archiving, moderation, soft deletion or an alcohol status change. Neither JWT
nor `RecipeAccess::View` was involved. Random filenames reduced discoverability
but were not access control. Removing a database reference did not revoke the
static URL.

Recipe images now follow the recipe's read permissions. Both development and
production FrankenPHP configurations deny all `/uploads/*` requests except
canonical avatar filenames. The API container enforces this too, independently
of the outer Caddy proxy. Hidden temporary files are never public.

`GET|HEAD /api/recipe-images/{filename}` resolves the current recipe reference
and checks the existing `RecipeAccess::View` voter before returning any bytes.
Missing, unreferenced or forbidden images return 404. The endpoint inherits all
existing voter behavior, including the administrator override already present
in `AlcoholAccessPolicy`; it does not add an image-specific privilege.
Successful responses use the real image Content-Type, `X-Content-Type-Options:
nosniff` and `Cache-Control: private, no-store`. Do not add public proxy/CDN
caching, a static alias, or an image optimizer that caches this endpoint.

The `imagePath` JSON field remains `/uploads/recipes/{filename}` as a storage
identifier for compatibility with existing rows. Frontend URL construction maps
it to the protected endpoint. Anonymous public images remain normal SSR `<img>`
URLs, also suitable for public OpenGraph images. Authenticated viewers initially
see the same placeholder on server and hydration, then fetch image Blobs through
the existing Bearer/refresh API client. Object URLs stay in component memory and
are revoked on image changes, logout and unmount. No tokens appear in image URLs
or persistent browser storage. Restricted OpenGraph URLs also require access;
anonymous crawlers cannot retrieve them. Avatars retain public delivery.

Previously downloaded bytes cannot be recalled, and a tab already displaying an
image is not remotely cleared when moderation changes. Every new API delivery
request reevaluates the current rights; no shared or browser HTTP cache is
permitted. Previously cached static URLs may remain in external caches until
their prior entries expire.

## Orphan maintenance

Preview only (the default also applies without `--dry-run`):

```bash
docker compose --env-file .env.prod -f compose.prod.yaml exec api \
  php bin/console app:uploads:clean --dry-run --grace-hours=24
```

The command scans only canonical generated image names and known temporary
names, without following symlinks or traversing subdirectories. Unknown files
are left alone. Every avatar and recipe reference is retained, including hidden
and soft-deleted entities. A candidate must be older than the grace period
(default 24 hours, minimum 1 hour). Dry-run lists candidates and changes no image
files. The shared coordination lock file may be created.

Uploads hold a shared filesystem maintenance lock through writing, commit and
cleanup. Maintenance takes the exclusive lock before reading references and
scanning files, preventing deletion of an upload whose transaction is in flight.
This is deliberately a single-VPS/local-filesystem design. Writers importing
image files outside the upload endpoints must use the same lock. Maintenance
fails closed if reference queries or locking fail.

After inspecting a preview and verifying backups, an operator may explicitly
replace `--dry-run` with `--delete`. These options are mutually exclusive.
No destructive purge was run during this implementation; tests of the delete
option use a mock storage adapter. Run previews manually at first; scheduling
and backup retention are deployment operations, not enabled automatically.

Use a longer grace period around backup/restoration or migrations. Restore the
database and volume together in an isolated environment and inspect a dry-run
before deleting anything. Monitor disk usage and cleanup warnings. The off-site
backup destination and retention schedule remain deployment choices.
