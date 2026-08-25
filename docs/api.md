# API Contract

This document is the internal V1 API contract consumed by the Nuxt frontend.

Base URL in Docker development:

```text
http://localhost:8080/api
```

All endpoints return JSON. Custom controller responses use stable plain JSON
objects.

API Platform supports both:

- `application/json`, where collections are returned as bare JSON arrays;
- `application/ld+json`, where collections use the Hydra/JSON-LD shape.

The Nuxt collection helpers accept bare arrays, Hydra collections, and custom
admin `{ "items": [] }` collections. New frontend calls should request or
expect simple JSON unless JSON-LD metadata is specifically needed.

## Authentication

```text
POST  /auth/register
POST  /auth/login
POST  /auth/refresh
GET   /me
PATCH /me
PATCH /me/password
POST  /me/avatar
GET   /users/{username}
```

There is currently no server-side logout endpoint. Nuxt logout clears the
in-memory access token and refresh-token session cookie. Because the refresh
token is not explicitly revoked in the API at logout time, server-side
revocation remains a production hardening task.

Soft-deleted accounts receive a generic `401 Unauthorized` response when they
attempt to log in, refresh a session, or use an existing access token. Applying
account deletion through an admin user mutation or report moderation also
revokes all refresh tokens for that account. Restoring the account allows it to
authenticate again, but does not restore revoked refresh tokens.

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
  "items": []
}
```

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
