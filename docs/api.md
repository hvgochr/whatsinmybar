# API Contract

This document is the internal V1 API contract consumed by the Nuxt frontend.

Base URL in Docker development:

```text
http://localhost:8080/api
```

Endpoints return JSON except the protected recipe image delivery endpoint.
Custom controller responses use stable plain JSON objects.

API Platform supports both:

- `application/json`, where collections are returned as bare JSON arrays;
- `application/ld+json`, where collections use the Hydra/JSON-LD shape.

The Nuxt collection helpers accept bare arrays, Hydra collections, and custom
`{ "items": [] }` collections. Paginated API Platform recipe calls MUST send
`Accept: application/ld+json`, including discovery, category recipes, and public
profile recipes (`author`). Bare array length is only the returned page length,
never a reliable paginated total. Item requests and custom controllers keep
simple JSON.

Recipe collections have 30 items per page and accept `page` (default `1`). The
JSON-LD response uses `member`, `totalItems`, and, when paginated, `view` with
`first`, `last`, `previous` and `next` links as applicable. The frontend helpers
also understand the equivalent `hydra:`-prefixed keys. For example, page 2 of
65 visible recipes contains 30 members, `totalItems: 65`, and a `view.last`
ending in `page=3`. Page 3 contains 5 members and no next link. An out-of-range
page succeeds with no members while retaining the total and last-page link.
An empty result has `totalItems: 0`, no members, and no next link. Totals and
links reflect the active filters and server-side visibility restrictions.

Frontend page navigation preserves the current URL query parameters and
filters; changing search filters resets the page to 1. Public profiles use
`page` for published recipes independently of `recipesPage` and `favoritesPage`
for private collections.

## Categories and ingredients

```text
GET /categories
GET /categories/{slug}
GET /ingredients
GET /ingredients/{slug}
```

Both taxonomy collections remain paginated by default (30 items). Only their
public collection operations authorize `pagination=false`. The frontend uses
this explicitly to retrieve the complete category directory and the categories
and ingredients used by search and recipe editor selectors. These calls use
simple JSON arrays. Recipes and other resources do not gain client-controlled
pagination disabling; sending `pagination=false` to `/recipes` still returns
one page. Admin collection pagination is unchanged.

The ingredient editor fetches `GET /ingredients/{slug}` directly; it never
searches a collection page for the item. A missing ingredient returns 404.

## Authentication

```text
POST  /auth/register
POST  /auth/login
POST  /auth/refresh
POST  /auth/logout
GET   /me
PATCH /me
PATCH /me/password
POST  /me/avatar
GET   /me/recipes
GET   /me/saved-recipes
GET   /users/{username}
```

Successful login and refresh responses contain only the short-lived access
token:

```json
{
  "token": "..."
}
```

The API stores the 30-day refresh token in a host-only `refresh_token` cookie
with `HttpOnly`, `SameSite=Strict`, `Path=/`, and `Secure` in production. The
root path allows Nuxt to receive the cookie on page requests and restore the
viewer session before server-rendered API requests. The API ignores the cookie
outside authentication endpoints.
The browser must include credentials on login, refresh, and logout requests.
Frontend JavaScript never receives or reads the refresh-token value.

`POST /auth/refresh` has no JSON payload. It consumes only the cookie (body/query tokens are ignored). Rotation uses a
PostgreSQL row lock and keeps the SHA-256 hash of one predecessor. For a fixed
10 seconds after rotation, requests using either that predecessor or its
successor receive the **same successor cookie**, with the same expiry. They do
not extend this window. After the window, the predecessor returns `401`; the
current token rotates again on its next use. This bounded repeatability covers
concurrent API calls, tabs, independent SSR requests and short lost-response
retries without an indefinitely reusable old token. Login continues to issue
refresh tokens through Gesdinet; the explicit Symfony session controller owns
refresh and logout rotation semantics.

`POST /auth/logout` revokes the row selected by the current token or its retained
predecessor and expires the cookie. It is idempotent even without a cookie. Both cookie-authenticated endpoints require this header:

```text
X-CSRF-Protection: 1
```

The non-simple header forces cross-origin browser requests through the
credentialed CORS policy; `SameSite=Strict` provides an additional cookie
boundary. Missing protection returns `403 Forbidden`.

Soft-deleted accounts receive a generic `401 Unauthorized` response when they
attempt to log in, refresh a session, or use an existing access token. Applying
account deletion through an admin user mutation or report moderation also
revokes all refresh tokens for that account. Restoring the account allows it to
authenticate again, but does not restore revoked refresh tokens.

Changing a password also revokes every refresh token for that account, including
rotation predecessors, and expires the calling browser's refresh cookie. On
confirmed success Nuxt clears its session and personalized data, informs other
open tabs and navigates to login with a success notice. Other devices must log
in again when refresh is next needed. Existing access tokens retain their
normal 15-minute lifetime; this change does not introduce immediate JWT
revocation.

Session failure and recovery:

- `401` from refresh means absent, expired, revoked or invalid credentials. Nuxt
  clears the access token, viewer and personalized data, without calling logout.
  A failed refresh does **not** emit `Set-Cookie`: a late failure must not erase
  another request's newer cookie. An invalid cookie may remain until expiry,
  explicit logout or the next login.
- Network errors, timeouts, `408`, `429` and `5xx` do not revoke the cookie.
  Nuxt enters a degraded state, hides uncertain personalized UI and offers
  recovery through a session banner or window focus. Public recipe reads can
  retry anonymously, subject to the normal backend alcohol/visibility rules.
  Protected reads and writes report the failure rather than silently retrying
  as anonymous. `403` (including a CSRF failure) is not treated as a logout or
  retried anonymously.
- Refresh has a 3-second client deadline, ordinary API calls 8 seconds and
  uploads 30 seconds, including calls with an explicit cancellation signal.
  Implicit ofetch retries are disabled. Refresh attempts share a Promise within
  one client, reuse an already refreshed access token for a late `401`, and
  back off for 5 seconds after failure (30 seconds for `429` without
  `Retry-After`). A supplied `Retry-After` is respected. PostgreSQL lock waits
  during refresh are limited to 2 seconds and return `503` with `Retry-After: 2`.
- Explicit logout waits for confirmed revocation; a temporary failure offers
  retry rather than claiming the cookie has been revoked.
- Browser tabs exchange only session-change notifications via BroadcastChannel,
  never credentials. The database rotation window provides concurrency safety
  independently of browser coordination. Pending personalized responses from a
  previous session are discarded; renewed tokens are rebound to `/me` before
  completing refresh (including when a tab changed the account). Viewer changes
  invalidate Nuxt data and remount page state. Routine renewal for an unchanged viewer preserves page
  state and unsaved editor input.

SSR and cache policy:

Nuxt state and its outgoing cookie jar belong to one SSR request. Only the
refresh cookie is forwarded, only to the authentication endpoints; subsequent
calls in that request use any newly issued cookie. Response cookies are relayed
as separate `Set-Cookie` headers, including on HTTP errors when present. The
refresh value is never serialized into the Nuxt payload. The short-lived JWT
and viewer can be present in personalized HTML/payloads.

All API responses and Nuxt-rendered HTML/payload responses use
`Cache-Control: private, no-store`. API responses vary on Authorization and
Cookie; Nuxt responses vary on Cookie. Do not enable shared HTML/payload caching,
SWR/ISR or authenticated prerendering without revisiting this policy. This
conservative policy deliberately gives up public API caching at low traffic.

The grace window is deliberately finite. A response/cookie delivered out of
order beyond the window, or a lost rotation response retried only after the
window, can require login again. Multi-region deployments, indefinite offline
recovery and immediate revocation of existing JWTs are outside this contract.

Register payload:

```json
{
  "email": "user@example.com",
  "username": "jane_doe",
  "password": "very-secure-password",
  "birthDate": "1990-01-01",
  "bio": "Home bartender"
}
```

Current user payload:

```json
{
  "id": 1,
  "email": "user@example.com",
  "username": "jane_doe",
  "birthDate": "1990-01-01",
  "bio": "Home bartender",
  "avatarPath": "/uploads/avatars/avatar.png",
  "roles": ["ROLE_USER"],
  "createdAt": "2026-07-25T10:00:00+00:00",
  "updatedAt": "2026-07-25T10:00:00+00:00"
}
```

Public profile payload excludes private account fields:

```json
{
  "id": 1,
  "username": "jane_doe",
  "bio": "Home bartender",
  "avatarPath": "/uploads/avatars/avatar.png",
  "createdAt": "2026-07-25T10:00:00+00:00"
}
```

Password change payload:

```json
{
  "currentPassword": "very-secure-password",
  "newPassword": "new-very-secure-password"
}
```

Avatar upload is multipart with the `avatar` file field.

`GET /me/recipes` and `GET /me/saved-recipes` are private account-library
collections. They do not accept a user identifier: the bearer token always
selects the current account. Both accept `page` and `pageSize` (default `20`,
maximum `100`) and return:

```json
{
  "items": [],
  "page": 1,
  "pageSize": 20,
  "totalItems": 0,
  "totalPages": 0
}
```

Owned recipes include the current user's visible drafts, published recipes,
and archived recipes, excluding soft-deleted recipes. Saved recipes include
only published, moderation-visible recipes. Both collections enforce alcohol
visibility, and each recipe summary includes the viewer-specific `favorited`
boolean.

## Recipes

```text
GET    /recipes
POST   /recipes
GET    /recipes/{slug}
PATCH  /recipes/{slug}
DELETE /recipes/{slug}
POST   /recipes/{slug}/publish
POST   /recipes/{slug}/archive
POST   /recipes/{slug}/image
DELETE /recipes/{slug}/image
POST   /recipes/aggregate
PUT    /recipes/{slug}/aggregate
```

The recipe editor writes recipe content through the aggregate endpoints. `POST`
creates a draft and `PUT` completely replaces the editable aggregate of an
existing recipe. Both operations require an authenticated recipe manager and
accept the same JSON object:

```json
{
  "title": "Negroni",
  "description": "A bitter, stirred classic.",
  "difficulty": "easy",
  "preparationTimeMinutes": 5,
  "servings": 1,
  "categories": ["/api/categories/classics"],
  "steps": [
    { "instruction": "Stir all ingredients with ice." },
    { "instruction": "Strain into a chilled glass." }
  ],
  "ingredients": [
    {
      "ingredient": "/api/ingredients/gin",
      "quantity": "30",
      "unit": "ml",
      "note": null
    }
  ]
}
```

`steps` and `ingredients` are required, non-empty arrays. Their array order is
the stored position, starting at `1`; clients do not send child IDs or
positions. Categories and ingredients are referenced by API IRI. Quantity is a
positive decimal string with at most six integer digits and two decimal places.

The complete payload, including all saved categories, ordered `steps`, and
ordered `recipeIngredients`, is validated before replacement. Metadata,
categories, steps, measured ingredients, and computed alcohol status are then
committed in one database transaction. A rejected or interrupted aggregate
write leaves the previous stored aggregate unchanged. The response is the full
`recipe:read` representation (`201` for create, `200` for update).

Image upload remains a separate multipart operation. Publication remains a
separate workflow operation and must only be requested after the aggregate
write succeeds.

Recipe collection query parameters:

```text
q
category
ingredient
alcohol
author
minFavorites
publishedAfter
publishedBefore
sort=popular|newest|oldest
```

Recipe item and collection representations include `favorited`. It is `true`
only when the authenticated viewer has saved that recipe; it is `false` for
anonymous viewers and other authenticated users.

The API contract for `alcohol` is boolean:

```text
alcohol=true
alcohol=false
```

The browser-facing route and search form use `with` and `without`. The typed
Nuxt API client maps those UI values to API booleans at the HTTP boundary.

Workflow response:

```json
{
  "id": 1,
  "title": "Negroni",
  "slug": "negroni",
  "status": "published",
  "moderationStatus": "visible",
  "publishedAt": "2026-07-25T10:00:00+00:00",
  "deleted": false,
  "deletedAt": null,
  "updatedAt": "2026-07-25T10:00:00+00:00"
}
```

Favorite response:

```json
{
  "recipeSlug": "negroni",
  "favoriteCount": 12,
  "favorited": true,
  "changed": true
}
```

Recipe image upload is multipart with the `image` file field.

`containsAlcoholComputed` and `containsAlcoholOverride` are read-only on the
general recipe create and update operations. The computed value follows the
recipe's ingredients. Only administrators can set or clear
`containsAlcoholOverride`, through `PATCH /admin/recipes/{slug}`.

## Comments

```text
GET    /recipes/{slug}/comments
POST   /recipes/{slug}/comments
PATCH  /comments/{id}
DELETE /comments/{id}
```

Comment payload:

```json
{
  "id": 1,
  "recipeSlug": "negroni",
  "authorUsername": "jane_doe",
  "parentId": null,
  "message": "Great recipe.",
  "moderationStatus": "visible",
  "replyCount": 0,
  "deleted": false,
  "createdAt": "2026-07-25T10:00:00+00:00",
  "updatedAt": "2026-07-25T10:00:00+00:00"
}
```

Comment creation accepts only `message` (required string, 2,000 characters
maximum) and `parentId` (optional positive integer or `null`). Comment updates
accept only `message` and `moderationStatus`; omitted fields remain unchanged.
Blank or non-string messages and undeclared fields return a `422` validation
error.

Deleted or hidden comments return `message: null`.

## Reports

```text
POST  /reports
GET   /admin/reports
PATCH /admin/reports/{id}
```

Report payload:

```json
{
  "id": 1,
  "reporterUsername": "jane_doe",
  "targetType": "recipe",
  "targetId": 42,
  "reason": "spam",
  "message": "Looks suspicious.",
  "status": "open",
  "reviewedByUsername": null,
  "reviewedAt": null,
  "createdAt": "2026-07-25T10:00:00+00:00",
  "updatedAt": "2026-07-25T10:00:00+00:00"
}
```

Report creation requires string `targetType` and `reason` fields plus a positive
integer `targetId`. The optional `message` must be a string or `null` and is
limited to 2,000 characters; blank messages are stored as `null`. Undeclared
fields return a `422` validation error.

## Admin

```text
GET   /admin/users
PATCH /admin/users/{id}
GET   /admin/recipes
PATCH /admin/recipes/{slug}
GET   /admin/categories
POST  /admin/categories
PATCH /admin/categories/{slug}
GET   /admin/ingredients
POST  /admin/ingredients
PATCH /admin/ingredients/{slug}
```

Admin list endpoints return:

```json
{
  "items": [],
  "page": 1,
  "pageSize": 20,
  "totalItems": 0,
  "totalPages": 0
}
```

All admin list endpoints accept positive integer `page` and `pageSize` query
parameters. `page` defaults to `1`; `pageSize` defaults to `20` and cannot
exceed `100`. Invalid values return the standard `400 bad_request` error. A
page beyond `totalPages` is successful and returns an empty `items` array while
preserving the requested page and total metadata. Results use a stable
descending timestamp order with the numeric ID as a descending tie-breaker.

Admin mutations are always protected server-side with `ROLE_ADMIN`.

## Errors

Expected V1 custom error shape:

```json
{
  "error": {
    "status": 400,
    "code": "bad_request",
    "message": "Invalid JSON body."
  }
}
```

Validation errors include `violations`:

```json
{
  "error": {
    "status": 422,
    "code": "validation_failed",
    "message": "Validation failed.",
    "violations": [
      {
        "property": "[email]",
        "message": "This value is not a valid email address."
      }
    ]
  }
}
```

## Image uploads and delivery

Avatar uploads accept at most 2 MiB; recipe images at most 5 MiB. Only decodable
JPEG, PNG and WebP images are accepted, at most 4096 pixels per side and
8,000,000 pixels total. Invalid images return `400 bad_request`. The API
resizes and reencodes them (maximum output side 512 for avatars, 1600 for
recipes) and strips original metadata. File paths remain server-generated and
cannot be assigned through JSON writes.

`POST /me/avatar`, `POST /recipes/{slug}/image`, and
`DELETE /recipes/{slug}/image` keep their existing JSON shapes. Replacing or
removing an image deletes the old unreferenced file only after database commit.
A failed write cleans up the new file where the database can confirm rollback.

```text
GET|HEAD /recipe-images/{filename}
```

`imagePath` remains `/uploads/recipes/{filename}` in recipe JSON, but this is a
storage identifier: direct HTTP access to it now returns 404. Construct delivery
URLs using `/api/recipe-images/{filename}`. That endpoint checks the current
recipe read permission (including age, workflow, moderation and deletion) and
returns 404 for missing references or denied access. Send the normal Bearer
header for authenticated access. Do not send tokens in URLs or use the refresh
cookie as an image credential. Anonymous users can retrieve only images of
recipes readable anonymously.

Successful delivery returns image bytes with `image/jpeg`, `image/png` or
`image/webp`, `Cache-Control: private, no-store` and
`X-Content-Type-Options: nosniff`. Avatars keep their public `/uploads/avatars/`
URLs. See [Local image storage](uploads.md) for operational details and limits.
## Recipe write invariants

General recipe `POST` and `PATCH` accept only their declared writable fields.
The `moderationStatus` field is now read-only on these operations. Read-only
fields (including alcohol classification and `imagePath`) and unknown fields
return `400`; previously read-only or unknown fields were silently ignored.
Recipe moderation is available only through administrator operations:
`PATCH /admin/recipes/{slug}` and report moderation. Comment moderation already
requires `ROLE_ADMIN`, including on `PATCH /comments/{id}`.

Publication is validated centrally for `POST /recipes` and
`PATCH /recipes/{slug}` with `status: published`,
`POST /recipes/{slug}/publish`, and
`PATCH /admin/recipes/{slug}`. A publishable recipe has valid metadata, at least
one non-blank step, at least one ingredient reference, positive positions unique
within each collection, and valid measured ingredients. Nullable quantities
remain supported for intentionally free-form amounts on individual writes;
aggregate quantities retain their stricter existing payload contract. Invalid
publication returns `422`, without storing the status or `publishedAt` change.
Editing published recipe content must preserve these conditions. Administrator
protection actions (hiding, removing, archiving, soft-deleting or correcting
alcohol classification) remain available for legacy incomplete published
recipes. Administrator mutations validate transitions into `published`, and
transitions from a non-visible moderation status to `visible` while published,
including report moderation. Sending an unchanged status does not itself trigger
publication validation. Refused restoration leaves both recipe and report data
unchanged.

Aggregate writes check alcohol access against the proposed ingredients and the
existing administrator override **before** any persistence or serialization.
A minor cannot create an alcoholic aggregate or turn an accessible recipe into
an alcoholic one, whether draft or published (`403`). The existing administrator
exception to alcohol access and override precedence are unchanged.

The individual operations remain available for compatibility:

```text
POST   /recipe_steps
PATCH  /recipe_steps/{id}
DELETE /recipe_steps/{id}
POST   /recipe_ingredients
PATCH  /recipe_ingredients/{id}
DELETE /recipe_ingredients/{id}
```

Their `recipe` IRI is required at creation and immutable thereafter, including
for administrators. Sending it in a `PATCH` returns `400`, even if unchanged.
Other undeclared fields also return `400`. Ownership is checked server-side,
and ingredient mutations recheck alcohol access against the proposed recipe.
Deleting the last step or ingredient of a published recipe returns `422`.
Allowed ingredient deletion updates alcohol classification in the same flush.
All refused writes leave persisted recipe metadata, relations and children
unchanged.

The Nuxt editor uses aggregate writes and does not call these individual
operations. They are nevertheless exposed API operations with functional tests;
removing them would be a separate contract change. No routes are removed here.
