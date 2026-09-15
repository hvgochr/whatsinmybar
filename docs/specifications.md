# whatsinmybar - Functional and Technical Specifications

## 1. Product Vision

whatsinmybar is a social cocktail recipe platform where users can discover, search, create, publish, save, comment on, and report cocktail recipes.

The V1 focuses on:

- public SEO-friendly discovery pages;
- user accounts with JWT authentication;
- recipe creation and publication workflows;
- robust alcohol access restrictions;
- favorites, threaded comments, and reporting/moderation;
- a Nuxt-based administration area;
- a simple Docker Compose deployment target for a VPS.

The application UI and SEO content are English-only for the V1.

## 2. Repository Structure

Current monorepo layout:

```text
apps/
  api/      Symfony 8.1 API Platform backend
  web/      Nuxt 4 frontend
docs/       project documentation
infra/      Docker, Caddy, deployment and infrastructure files
```

## 3. V1 Scope

### 3.1 Public Pages

The frontend must expose SEO-friendly pages:

- home page;
- recipe list;
- recipe detail page;
- category list;
- category detail page;
- public user profile page.

Public URLs must use clean slugs:

- `/recipes`
- `/recipes/{recipeSlug}`
- `/categories`
- `/categories/{categorySlug}`
- `/users/{username}`

Each public page must provide:

- server-rendered content where relevant;
- OpenGraph metadata;
- canonical URLs;
- sitemap inclusion;
- useful title and description metadata.

### 3.2 Search And Filters

Recipe search and filtering are server-side.

Supported filters:

- text query;
- category;
- ingredient;
- alcohol / non-alcohol;
- author;
- popularity;
- publication date.

Search is SQL-based for V1. PostgreSQL full-text search can be introduced when needed, but no dedicated search engine is planned for V1.

Popularity is based on favorite count only.

### 3.3 Accounts

Users can:

- register;
- log in;
- refresh their session;
- log out;
- edit their profile;
- upload/update their avatar;
- provide their birth date.

Birth date is mandatory at registration because it is required for alcohol access control.

The public identity model uses:

- unique email for authentication;
- unique username/login for public URLs and display;
- optional bio/status.

### 3.4 Recipes

Authenticated users can:

- create recipes;
- save drafts;
- publish recipes;
- archive recipes;
- edit their recipes;
- soft-delete their recipes;
- upload a main recipe image;
- define ordered preparation steps;
- define measured ingredients;
- assign categories.

Recipe statuses:

- `draft`: visible only to the author and admins;
- `published`: visible publicly, subject to alcohol restrictions;
- `archived`: hidden from public listings, preserved for history/admin.

Recipe deletion is soft deletion.

Publication requires valid recipe metadata, at least one valid preparation step,
and at least one valid measured ingredient. Positions must be positive and unique
within each ordered collection. These conditions apply to every publication
endpoint and must remain true when published content is edited. Administrators
can still protect legacy incomplete published content by hiding, removing,
archiving, soft-deleting or correcting its alcohol classification. Publishing or
restoring public moderation visibility requires valid content. Drafts created
through individual operations can be assembled incrementally; the aggregate
editor retains its complete-payload requirement.

A step or measured ingredient cannot be reassigned to another recipe through
API updates. Recipe moderation status is writable only through administrator
operations. Alcohol access checks apply to the proposed state of a write before
persistence and serialization, including creation and ingredient replacement.
Refused writes must not change persisted data.

### 3.5 Comments

Authenticated users can comment on published recipes.

Comments are threaded:

- a comment can have a nullable parent comment;
- replies belong to the same recipe as their parent;
- new threads are limited to three levels and the API reports each comment's
  one-based depth;
- comment reads are paginated flat collections (20 by default, 100 maximum),
  so rendering work and response size stay bounded.

Authors can edit and soft-delete their own comments.

Admins can edit, hide, moderate, or soft-delete comments.

### 3.6 Favorites

Authenticated users can add or remove recipes from favorites.

Favorite operations must be idempotent:

- adding an already favorited recipe is a no-op success;
- removing a non-favorited recipe is a no-op success.

A unique database constraint must enforce one favorite per `(user, recipe)`.

### 3.7 Reports And Moderation

V1 includes reporting/moderation foundations.

Users can report:

- recipes;
- comments;
- user profiles.

Report status values:

- `open`;
- `reviewing`;
- `resolved`;
- `rejected`.

Admins can list reports, review the target content, update report status, and apply moderation actions.
Admin report responses include current target context, including the original
message of a hidden or deleted comment. That context is never included in the
authenticated reporter response or any public content response.

Moderation statuses for content should be explicit rather than inferred only from deletion:

- `visible`;
- `hidden`;
- `pending_review`;
- `removed`.

### 3.8 Admin

The admin area is built in Nuxt, not EasyAdmin.

Admin features:

- users management;
- recipes management;
- categories management;
- ingredients management;
- reports and moderation.

The API must expose admin-only operations with role checks. The frontend admin routes are only a UI layer and must not be trusted for authorization.
The last active administrator cannot be deleted or demoted; concurrent admin
mutations are serialized around this invariant.

## 4. Alcohol Access Rules

Alcohol restriction is a core security rule.

Access policy:

- anonymous users never see alcoholic recipes;
- authenticated users under 18 never see alcoholic recipes;
- authenticated users aged 18 or older can access alcoholic recipes;
- V1 only uses the `age >= 18` rule, with no country-specific legal rules.

The rule must be enforced both:

- in collection endpoints, so restricted recipes do not appear in lists/search;
- in item/detail endpoints, so direct URL/API access is denied.

Frontend filtering is not sufficient. The backend is the source of truth.

Alcohol status of a recipe:

- by default, computed from its ingredients: if at least one ingredient contains alcohol, the recipe contains alcohol;
- an admin override is allowed when the computed value is not enough.

Recommended backend fields:

- `Recipe.containsAlcoholComputed`;
- `Recipe.containsAlcoholOverride`, nullable boolean;
- exposed alcohol flag = override when set, otherwise computed value.

## 5. Data Model

### 5.1 User

Fields:

- `id`;
- `email`, unique;
- `username`, unique;
- `password`;
- `roles`;
- `birthDate`;
- `bio`;
- `avatarPath` or `avatarUrl`;
- `createdAt`;
- `updatedAt`;
- `deletedAt`, nullable.

Removed from V1:

- `premiumUntil`;
- `premiumStatus`;
- pro roles;
- payment-related fields.

### 5.2 Recipe

Fields:

- `id`;
- `author`;
- `title`;
- `slug`, unique;
- `description`;
- `difficulty`;
- `preparationTimeMinutes`;
- `servings`;
- `status`: `draft`, `published`, `archived`;
- `containsAlcoholComputed`;
- `containsAlcoholOverride`, nullable;
- `imagePath` or `imageUrl`;
- `publishedAt`, nullable;
- `createdAt`;
- `updatedAt`;
- `deletedAt`, nullable.

Recommended indexes:

- `slug`;
- `status`;
- `publishedAt`;
- `author_id`;
- alcohol visibility fields;
- search fields needed for SQL filtering.

### 5.3 RecipeStep

Fields:

- `id`;
- `recipe`;
- `position`;
- `instruction`.

Constraints:

- unique `(recipe_id, position)`;
- position starts at `1`;
- steps are returned sorted by `position`.

### 5.4 Ingredient

Fields:

- `id`;
- `name`;
- `slug`, unique;
- `containsAlcohol`;
- `createdAt`;
- `updatedAt`.

### 5.5 RecipeIngredient

Fields:

- `id`;
- `recipe`;
- `ingredient`;
- `quantity`, decimal nullable when the amount is intentionally free-form;
- `unit`;
- `position`;
- `note`, nullable.

Unit is an enum. Initial values:

- `ml`;
- `cl`;
- `l`;
- `oz`;
- `dash`;
- `bar_spoon`;
- `tsp`;
- `tbsp`;
- `drop`;
- `piece`;
- `slice`;
- `wedge`;
- `leaf`;
- `sprig`;
- `pinch`;
- `to_taste`.

Constraints:

- unique `(recipe_id, position)`;
- recipe ingredients are returned sorted by `position`.

### 5.6 Category

Fields:

- `id`;
- `name`;
- `slug`, unique;
- `description`;
- `createdAt`;
- `updatedAt`.

### 5.7 RecipeCategory

Join between recipes and categories.

Constraints:

- unique `(recipe_id, category_id)`.

This can be implemented either as an explicit entity or a Doctrine many-to-many join table. Use an explicit entity only if metadata is expected on the relation.

### 5.8 Comment

Fields:

- `id`;
- `recipe`;
- `author`;
- `parent`, nullable;
- `message`;
- `moderationStatus`;
- `createdAt`;
- `updatedAt`;
- `deletedAt`, nullable.

Rules:

- parent comment must belong to the same recipe;
- deleted comments can remain visible as placeholders if they have replies;
- hidden/removed comments must not expose the original message publicly.

### 5.9 Favorite

Fields:

- `id`;
- `user`;
- `recipe`;
- `createdAt`.

Constraints:

- unique `(user_id, recipe_id)`.

### 5.10 Report

Fields:

- `id`;
- `reporter`;
- `targetType`: `recipe`, `comment`, `user`;
- `targetId`;
- `reason`;
- `message`, nullable;
- `status`;
- `reviewedBy`, nullable;
- `reviewedAt`, nullable;
- `createdAt`;
- `updatedAt`.

Reason can be an enum, for example:

- `spam`;
- `abuse`;
- `illegal_content`;
- `wrong_alcohol_classification`;
- `copyright`;
- `other`.

### 5.11 Removed Payment Model

The following V1 model is explicitly out of scope:

- `Payment`;
- checkout sessions;
- payment intents;
- premium plans;
- premium roles/status.

## 6. Authentication And Authorization

Backend authentication:

- LexikJWTAuthenticationBundle for access tokens;
- gesdinet/jwt-refresh-token-bundle for refresh tokens.

Preferred frontend security approach:

- do not store long-lived tokens in `localStorage`;
- use secure HTTP-only cookies where feasible;
- keep access token lifetime short;
- rotate refresh tokens;
- invalidate refresh tokens on logout.

The final V1 transport keeps the access token in Nuxt memory and the refresh
token in a host-only, HttpOnly cookie scoped to `/api/auth`. The cookie uses
`SameSite=Strict` and is `Secure` in production. Refresh tokens rotate on refresh
with a fixed 10-second repeat window for concurrent requests (see `docs/api.md`); their values are omitted from JSON responses. Refresh
and logout require a custom anti-CSRF header and credentialed, origin-restricted
CORS. Server-side logout revokes the current refresh token, and password changes
revoke every refresh token for the account.

Soft-deleted accounts cannot authenticate with a password, refresh a session,
or authorize API requests with an access token issued before deletion. Applying
account deletion through either administration path revokes all refresh tokens;
restoration permits new authentication without restoring those tokens.

Roles:

- `ROLE_USER`;
- `ROLE_ADMIN`.

Authorization rules:

- anonymous users can read only public, published, non-alcoholic recipes;
- authenticated users under 18 can read only public, published, non-alcoholic recipes;
- authenticated users aged 18+ can read public, published recipes including alcoholic recipes;
- authors can manage their own drafts and recipes;
- admins can manage users, recipes, categories, ingredients, comments, and reports;
- admin API operations must always be protected server-side.

## 7. API Design

The backend is Symfony API Platform.

The frontend should consume simple JSON where possible, even if API Platform remains the base framework.

Recommended API groups:

- public read operations;
- authenticated user operations;
- owner write operations;
- admin operations.

Representative endpoints:

```text
POST   /auth/register
POST   /auth/login
POST   /auth/refresh
GET    /me
PATCH  /me
PATCH  /me/password
POST   /me/avatar
GET    /users/{username}

GET    /recipes
POST   /recipes
GET    /recipes/{slug}
PATCH  /recipes/{slug}
DELETE /recipes/{slug}
POST   /recipes/{slug}/image
DELETE /recipes/{slug}/image

POST   /recipes/aggregate
PUT    /recipes/{slug}/aggregate

POST   /recipes/{slug}/publish
POST   /recipes/{slug}/archive

POST   /recipes/{slug}/favorite
DELETE /recipes/{slug}/favorite

GET    /recipes/{slug}/comments
POST   /recipes/{slug}/comments
PATCH  /comments/{id}
DELETE /comments/{id}

GET    /categories
GET    /categories/{slug}

GET    /ingredients

POST   /reports

GET    /admin/users
PATCH  /admin/users/{id}
GET    /admin/recipes
PATCH  /admin/recipes/{slug}
GET    /admin/categories
POST   /admin/categories
PATCH  /admin/categories/{slug}
GET    /admin/ingredients
POST   /admin/ingredients
PATCH  /admin/ingredients/{slug}
GET    /admin/reports
PATCH  /admin/reports/{id}
```

Exact API Platform route shapes may differ, but the contract should preserve these capabilities.

Recipe editor creates and updates use the aggregate routes. Recipe metadata,
categories, ordered steps, and ordered measured ingredients are validated and
stored atomically. Image upload and publication remain separate operations;
publication is attempted only after a successful aggregate write.

User reports can apply profile moderation through `PATCH /admin/reports/{id}` with `moderationStatus=removed` to soft-delete a profile or `moderationStatus=visible` to restore it.

Recipe workflow endpoints return simple JSON payloads for the updated recipe workflow state. Full recipe creation, update, detail, and collection responses remain backed by API Platform serialization for V1.

Recipe collection query parameters:

- `q`: case-insensitive SQL text search on title, description, and slug;
- `category`: category slug;
- `ingredient`: ingredient slug;
- `alcohol`: boolean value, filtered after access-control restrictions;
- `author`: author username;
- `minFavorites`: minimum favorite count;
- `publishedAfter`: `YYYY-MM-DD`, inclusive;
- `publishedBefore`: `YYYY-MM-DD`, inclusive;
- `sort`: `popular`, `newest`, or `oldest`.

## 8. Frontend

Frontend stack:

- Nuxt 4;
- Vue 3;
- pnpm;
- Vitest for unit/component tests;
- Playwright for smoke E2E tests.

Main route groups:

- public discovery pages;
- authentication pages;
- authenticated user dashboard/profile;
- recipe editor;
- admin area.

Frontend responsibilities:

- SSR/SEO for public pages;
- typed API client layer;
- forms and validation;
- optimistic favorite toggles where safe;
- admin moderation workflows;
- clear handling of alcohol-restricted content.

Alcohol-restricted content should not be teased with direct details to restricted users. If a user opens a direct alcoholic recipe URL without access, show a generic not-found or restricted-access state based on product preference, while the API must still enforce the rule.

## 9. Images And File Storage

Use an abstraction for uploaded files.

Development:

- local filesystem storage;
- Docker volume for uploaded files.

Production:

- local filesystem storage on the single VPS is the accepted production choice;
- a persistent Docker volume retains uploads across container replacements;
- independent off-site backups and restoration testing are required;
- S3 is not part of this deployment scope.

Stored image types:

- user avatars;
- recipe main images.

V1 upload endpoints accept multipart image files and return storage paths.
Recipe images follow exactly the recipe read permissions and are delivered by
a protected API endpoint; avatars retain public delivery. Direct JSON writes to
image path fields should be rejected or ignored so type and file size
validation cannot be bypassed.

The API validates byte size, supported image type, dimensions and pixel count
before requiring a successful GD decode. Accepted images are resized and
reencoded without original metadata. See [Local image storage](uploads.md) for
limits, delivery rules and orphan maintenance.

The frontend should render responsive image sizes where possible.

## 10. Infrastructure

Target deployment:

- VPS;
- Docker Compose;
- Caddy as reverse proxy;
- PostgreSQL database.

Expected services:

```text
caddy
api        Symfony/PHP-FPM or FrankenPHP
web        Nuxt server
postgres
```

The repository now includes:

- multi-stage production targets for Symfony/FrankenPHP and Nuxt/Nitro;
- `compose.prod.yaml` with no application source bind mounts;
- Caddy automatic HTTPS and reverse proxy routing;
- private PostgreSQL networking;
- service healthchecks and restart policies;
- named volumes for PostgreSQL, Caddy data, and current local uploads;
- a production environment template and manual deployment runbook;
- a targeted CI workflow that validates Compose/Caddy and builds both production images.

Optional later services:

- worker/queue service;
- mailer catcher in development;
- object storage emulator such as MinIO;
- monitoring/logging stack.

Caddy responsibilities:

- TLS termination;
- route API traffic to Symfony;
- route web traffic to Nuxt;
- serve uploaded files only if local storage is used and access rules allow it.

## 11. CI

CI runs on GitHub Actions.

No automated deployment is required for V1.

### 11.1 Backend CI

Backend checks:

```text
composer validate
composer audit
PHPStan or Psalm
PHP-CS-Fixer
PHPUnit
doctrine:schema:validate
migrations dry-run
```

Recommended implementation:

- use PostgreSQL service container in CI;
- run migrations before schema validation;
- fail on missing migrations or invalid mapping;
- cache Composer dependencies.

### 11.2 Frontend CI

Frontend checks:

```text
pnpm install --frozen-lockfile
ESLint
typecheck
Vitest unit/component tests
Nuxt build
Playwright smoke tests
```

Recommended implementation:

- use the pinned pnpm version from `packageManager`;
- cache pnpm store;
- run Playwright against a built Nuxt preview or dev server;
- keep smoke tests small and focused on critical routes.

## 12. Non-Functional Requirements

Security:

- backend authorization is mandatory for all restricted actions;
- alcohol restriction is enforced at query and item access levels;
- passwords are hashed with Symfony's recommended password hasher;
- CORS is limited to allowed frontend origins;
- uploaded files are validated and never blindly trusted.

Performance:

- paginate recipe lists and admin lists;
- paginate comment collections and batch direct-reply counts;
- index common filters;
- avoid N+1 queries for recipe detail pages;
- cache public metadata where appropriate later.

Maintainability:

- keep API Platform resources explicit and grouped by use case;
- keep domain enums centralized;
- prefer migrations for all schema changes;
- keep frontend API calls behind a typed client/composable layer.

Accessibility:

- public and authenticated pages should be keyboard navigable;
- forms must expose clear validation errors;
- image uploads require meaningful alt text where relevant.

## 13. Implementation Status

Implemented:

1. Docker development environment with PostgreSQL, Caddy, FrankenPHP, and Nuxt.
2. Backend and frontend quality baselines with GitHub Actions CI.
3. JWT authentication, refresh, profile management, avatar upload, and password change.
4. Ingredients, categories, measurement units, recipes, workflow states, steps, and measured ingredients.
5. Alcohol computation, admin override, collection filtering, and item access enforcement.
6. Recipe search, server filters, sorting, and frontend filter UI.
7. Favorites, threaded comments, reports, moderation, and Nuxt administration.
8. Public SSR pages with canonical and OpenGraph metadata.
9. Local avatar and recipe image storage behind storage interfaces.
10. Multi-stage production images, production Compose, Caddy HTTPS routing, healthchecks, and a manual VPS runbook.

Remaining before the V1 production launch:

1. Implement the dynamic sitemap required by the SEO specification.
2. Configure off-site backups and retention for the accepted local upload storage.
3. Provision the VPS and complete DNS, firewall, SSH hardening, real TLS, monitoring, log retention, and off-site backups.
4. Test database and upload restoration on an isolated environment.
5. Define an immutable image registry and rollback process if deployments move beyond manual source builds.
6. Run a final accessibility, responsive layout, security, and end-to-end acceptance pass.

## 14. Open Decisions

The following details still require a product or infrastructure decision:

- whether recipe and category slugs become immutable after publication;
- public recipe pagination versus infinite loading as the long-term interaction;
- VPS provider, domain, monitoring provider, and off-site backup destination.
