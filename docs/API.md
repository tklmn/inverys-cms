# REST API (`/api/v1`)

The CMS ships a versioned REST API under `/api/v1`. It supports two response
representations that a client selects **per request** via the `Accept` header:

| `Accept` header | Representation |
| --- | --- |
| *(any, default)* | Laravel API resources — flat attributes under `data` |
| `application/vnd.api+json` | [JSON:API 1.1](https://jsonapi.org/format/) documents |

The default representation is the baseline contract. JSON:API is strictly
**opt-in**: a client that does not send the JSON:API media type always receives the
default representation.

## Authentication

All endpoints except `POST /api/v1/auth/login` require a bearer token:

```
Authorization: Bearer <token>
```

Tokens are issued in the backend under **API Tokens**. Every request additionally
requires the `api-access` permission, and individual endpoints require their own
permission (e.g. `show-page`, `create-page`). Tokens are hashed at rest; the plain
text value is shown only once at creation.

- **Scope:** a backend token is *full*, *read-write* or *read-only*. A read-only
  token may only send `GET`, `HEAD` and `OPTIONS` requests; anything else answers
  `403`. An optional expiry is set in days.
- **Login:** `POST /api/v1/auth/login` with `email` and `password` (optional
  `token_name`) returns `token`, `expires_at` (24 hours) and `user`. It uses the
  same lockout as the browser login (`security.login_max_attempts`,
  `security.login_lockout_duration`) and is throttled to 5 requests per minute.
- **Two-factor:** if the account needs a second factor, the first call answers
  `202` with `two_factor_required: true` and `method` (`email` sends a code, at
  most one mail per minute). Repeat the call with `two_factor_code` (and
  `use_recovery: true` for a recovery code). A wrong code answers `401` and
  counts as a failed attempt.
- **Errors:** `401` without, with an invalid or with an expired token; `403` for
  an inactive account; `429` with `retry_after` when locked out.
- `POST /api/v1/auth/logout` deletes the token that was used.

All API requests are rate limited per minute (Settings → Security,
`security.api_rate_limit`, default 60, at least 10). Responses carry
`X-RateLimit-Limit` and `X-RateLimit-Remaining`; above the limit the API answers
`429` with `retry_after` and a `Retry-After` header.

## Endpoints

All paths are relative to `/api/v1`.

| Method and path | Permission | Notes |
| --- | --- | --- |
| `POST auth/login` | — | See [Authentication](#authentication) |
| `POST auth/logout` | — | Deletes the current token |
| `GET auth/me` | — | The token's user |
| `GET pages` | `show-page` | `search` (title, slug), `status` (`active`/`inactive`), `parent_id`, `per_page` (default 15, max 100); sorted by `order` |
| `GET pages/{id}` | `show-page` | |
| `POST pages` | `create-page` | `201`; an empty `slug` is generated from the title; see [Page content](#page-content) |
| `PUT pages/{id}` | `edit-page` | `422` when the page is moved under itself or a descendant |
| `DELETE pages/{id}` | `delete-page` | Moves the page to the trash; the home page answers `422` |
| `GET media` | `show-media` | `type` (`image`, `document`, `video`, `audio`), `search`, `per_page` (default 20, max 100); newest first |
| `GET media/{id}` | `show-media` | |
| `POST media` | `create-media` | Multipart: `file` (same file types and size limit as the backend upload), `alt_text`, `description`; `201` |
| `DELETE media/{id}` | `delete-media` | Moves the item to the trash; the files are removed only on permanent deletion |
| `GET users`, `GET users/{id}` | `show-user` | Read-only; `search` (name, email), `per_page` (default 15, max 100) |
| `GET settings` | `show-settings` | See [Settings](#settings) |
| `PUT settings/{key}` | `edit-settings` | Body `{ "value": … }`; see [Settings](#settings) |
| `GET blocks/types` | `manage-blocks` | |
| `GET themes` | `manage-themes` | The active theme's slug in `active` (JSON:API: `meta.active`) |
| `PUT themes/active` | `manage-themes` | Body `{ "slug": … }`; `404` for an unknown theme |
| `GET plugins` | `manage-plugins` | |
| `PUT plugins/{slug}` | `manage-plugins` | Body `{ "enabled": true }` or `false`; `404` for an unknown plugin |

## Default representation

A single resource is wrapped in `data`; collections add pagination `links` and
`meta`:

```jsonc
// GET /api/v1/pages/1
{
  "data": {
    "id": 1,
    "title": "Home",
    "slug": "home",
    "is_active": true
    // …
  }
}
```

```jsonc
// GET /api/v1/pages
{
  "data": [ { "id": 1, "title": "Home", /* … */ } ],
  "links": { "first": "…", "last": "…", "prev": null, "next": null },
  "meta":  { "current_page": 1, "per_page": 15, "total": 1, /* … */ }
}
```

Errors use Laravel's conventional shape:

```jsonc
{ "message": "The title field is required.", "errors": { "title": ["…"] } }
```

## JSON:API representation (opt-in)

Send `Accept: application/vnd.api+json` to receive JSON:API documents. Responses are
returned with `Content-Type: application/vnd.api+json` and a top-level `jsonapi`
member.

### Resource objects

Each resource becomes a `{ type, id, attributes }` object. `id` is always a string,
and — per the specification — the `id` and `type` fields never appear inside
`attributes`.

```jsonc
// GET /api/v1/pages/1   (Accept: application/vnd.api+json)
{
  "jsonapi": { "version": "1.1" },
  "data": {
    "type": "pages",
    "id": "1",
    "attributes": {
      "title": "Home",
      "slug": "home",
      "is_active": true
      // …
    }
  }
}
```

Collections return `data` as an array and keep pagination under top-level `links`
and `meta`.

### Resource types and identifiers

| Endpoint | `type` | `id` |
| --- | --- | --- |
| `pages` | `pages` | page id |
| `media` | `media` | media id |
| `users` | `users` | user id |
| `settings` | `settings` | setting id |
| `blocks/types` | `block-types` | block type id |
| `themes` | `themes` | theme **slug** (themes are config-backed, not database rows) |
| `plugins` | `plugins` | plugin id |

Because JSON:API reserves the field name `type`, two resources rename their internal
`type` field inside `attributes`:

- **media**: the media kind is exposed as `media_type`.
- **settings**: the value cast type is exposed as `value_type`.

Any non-standard top-level member returned by a controller is relocated under `meta`.
For example `GET /api/v1/themes` returns the active theme as `meta.active`.

### Errors

Errors follow the JSON:API [error object](https://jsonapi.org/format/#error-objects)
shape. Validation failures include an attribute source pointer per failed field:

```jsonc
// 422 Unprocessable Content
{
  "jsonapi": { "version": "1.1" },
  "errors": [
    {
      "status": "422",
      "title": "The title field is required.",
      "detail": "The title field is required.",
      "source": { "pointer": "/data/attributes/title" }
    }
  ]
}
```

## Page content

Pages consist of blocks — there is one editor, no HTML mode.

- Send the content as `blocks` (the same JSON the page editor stores). The
  response always has `content_type: "blocks"`.
- `content` in a response is the **plain text** of the blocks (for search and
  short descriptions), not HTML to render.
- HTML is still accepted for convenience: `POST /api/v1/pages` or
  `PUT /api/v1/pages/{id}` with `content` and **without** `blocks` stores the
  HTML as a single Text block (`content_type: "html"` is treated the same way).
  On an update this replaces the existing blocks.

## Settings

`GET /api/v1/settings` (`show-settings`) lists the settings except sensitive
groups (`mail.`, `imap.`, `notification.`, `security.`, plugin prefixes) and
`notification_email`, and never returns encrypted values. Each entry has `id`,
`key`, `value`, `type` (JSON:API: `value_type`) and `description`.

`PUT /api/v1/settings/{key}` (`edit-settings`) changes only the general
settings (Settings → General) with the same validation as the backend
(`App\Support\GeneralSettings`):

| Key | Accepted `value` |
| --- | --- |
| `site_name` | Text, up to 255 characters |
| `language` | An installed language |
| `timezone` | A real time zone |
| `date_format` | Up to 50 characters; empty = format of the interface language |
| `theme` | `light` or `dark` |
| `favicon` | A path (letters, digits, `.`, `_`, `/`, `-`; no `..`) |
| `revisions.max_per_model` | Integer 0–1000 |
| `html.minify` | `1` (on) or `0` (off) |
| `html.protect_emails` | `1` (on) or `0` (off) |

Everything else answers `403`:

- `active_theme` has its own endpoint: `PUT /api/v1/themes/active`
  (`manage-themes`, checks that the theme exists).
- `language` is refused while the Multilingual plugin is active — the default
  language is then managed on its **Languages** page.
- Keys with their own settings page (search, SEO, security, plugins) cannot be
  written through this endpoint.

## How it works

- `App\Http\Middleware\NegotiateJsonApi` (prepended to the `api` middleware group,
  ahead of `ForceJsonResponse`) records the opt-in on the request and, on the way out,
  applies the cross-cutting document rules: content type, the `jsonapi` member,
  folding non-standard members into `meta`, re-indexing keyed collections, and
  rewriting error payloads.
- `App\Http\Resources\JsonApiResource` is the base for all API resources. It renders
  the default flat shape and the `{ type, id, attributes }` shape when the request
  opted in. Subclasses declare `resourceType()` and provide the default payload via
  `data()`.
- `App\Support\JsonApi::requested()` reports whether the current request opted in.

## Not implemented

The following optional JSON:API features are intentionally out of scope for now.
Requests that use them are ignored rather than rejected:

- Compound documents / `include` (related resources are exposed as attribute ids,
  e.g. `parent_id`, not as `relationships`).
- Sparse fieldsets (`fields[type]`).
- JSON:API-defined `sort` / `filter` / `page` query parameters (endpoints keep their
  existing `search`, `status`, `per_page` parameters).
- Per-resource `links.self`.
