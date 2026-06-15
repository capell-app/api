# API

<!-- prettier-ignore-start -->

## What This Plugin Adds

API is an **Available**, **No schema impact** Capell package in the **Capell Publishing Pro** product group. It ships as `capell-app/api` and extends these surfaces: frontend.

Versioned public JSON endpoints for published Capell pages and Layout Builder graphs, with sanitized output for headless consumers.

After install, the package affects public rendering, public routes, or frontend runtime behaviour.

Status details:

- Status: Available
- Tier: premium
- Bundle: publishing-pro
- Composer package: `capell-app/api`
- Namespace: `Capell\Api`
- Theme key: not applicable

## Why It Matters

**For developers:** The package gives developers package-owned service providers, Actions, Data objects, Laravel routes, and a maintained OpenAPI contract instead of pushing this behaviour into core or application code. The canonical public endpoint is `GET /api/capell/v1/pages/resolve`; the legacy `/api/capell/pages/resolve` route remains available for compatibility.

**For teams:** Published Capell content can feed headless frontends and other public consumers without exposing editor controls, admin URLs, signed editor links, or unsanitized authoring HTML.

## Screens And Workflow

Screenshot contract: `screenshots.json`.

- Successful page resolve JSON response (frontend, required).
- Page resolve layout graph JSON response (frontend, required).
- Forbidden and not-found JSON responses with `expectedStatus` metadata (frontend, optional).

These JSON captures are deployment-runner evidence for the public endpoint
contract. Marketplace media should keep using styled extension assets from
`assets/marketplace/` unless a first-party endpoint explorer is added.

The canonical public route is `GET /api/capell/v1/pages/resolve`; the older
`GET /api/capell/pages/resolve` route is retained as legacy compatibility for
existing integrations. Both routes resolve the same sanitized published-page
payload, but new clients should use the v1 route and treat the legacy path as a
migration bridge.

Machine-readable contract: `openapi.yaml`. Successful responses include `ETag`,
and matching `If-None-Match` requests return `304 Not Modified`.

## Technical Shape

- Service providers: `Capell\Api\Providers\ApiServiceProvider`.
- Config files: `packages/api/config/capell-api.php`.
- Route files: `packages/api/routes/api.php`.
- OpenAPI contract: `packages/api/docs/openapi.yaml`.
- Actions: `BuildPublicLayoutPayloadAction`, `BuildPublicPagePayloadAction`.
- Data objects: `PublicPagePayloadOptionsData`.
- Manifest contributions: `health-check: Capell\Api\Health\ApiHealthCheck`, `route: Capell\Api\Manifest\ApiRoutesContribution` with public API endpoint metadata for the canonical v1 and legacy page resolver routes.
- Health checks: `Capell\Api\Health\ApiHealthCheck`.
- Cache tags: `api`, `api:pages`.

## Data Model

This package has no schema impact. It does not declare package-owned migrations or required tables.

Docs gap: document extension points here if the package delegates persistence to a host package.

## Install Impact

- Admin navigation: no admin surface declared.
- Permissions: none declared in `capell.json`.
- Public routes: `capell-api.v1.pages.resolve` is the canonical public read-only endpoint; `capell-api.pages.resolve` is legacy compatibility. Both use the API middleware stack and `throttle:capell-api` by default.
- Database changes: no package migrations declared.
- Settings: no package settings declared.
- Queues or schedules: none detected in standard package paths.
- Cache tags: `api`, `api:pages`.
- Commands: none declared.

## Common Pitfalls

- Review route middleware, throttling, signed URLs, and public-output safety before exposing routes.
- Keep public Blade and cached HTML free of authoring markers, model IDs, permissions, signed editor URLs, and lazy database queries.
- Point new integrations at `capell-api.v1.pages.resolve`; keep the legacy route only for compatibility.
- Keep `composer.json`, `composer.local.json`, `capell.json`, docs, screenshots, and tests aligned when the package surface changes.

## Troubleshooting

| Symptom | Likely cause | Check | Fix |
| --- | --- | --- | --- |
| Package surface is missing after install | Provider or manifest is not loaded | Confirm `capell.json`, package `composer.json`, and provider registration | Reinstall the package, refresh Composer autoload, and clear host caches |
| Route returns unexpected output | Route cache, middleware, or signed URL setup does not match the package route file | Check the route files listed in `Technical Shape` | Clear route cache and verify middleware before exposing public routes |
| Public output leaks unexpected state | Render data, cache variation, or authoring boundary has regressed | Check public Blade, cache tags, and public-output safety tests | Move data loading out of Blade and rerun the package public-output tests |

## Quick Start

1. Install the package: `composer require capell-app/api`.
2. Run the required setup: no package migrations are declared; clear cached config and routes if the host app uses caches.
3. Verify the package provider is registered and the related frontend, command, or extension point is active.

## Next Steps

- [Package docs index](README.md)
- [OpenAPI contract](openapi.yaml)
- [Screenshot contract](screenshots.json)
- [Marketplace assets](assets/marketplace/)
- [Capell content language plan](../../../docs/CONTENT_LANGUAGE_PLAN.md)
- [Capell documentation design system](../../../docs/DESIGN_SYSTEM.md)
- [Capell and package ERD notes](../../../docs/erd/capell-and-package-erds.md)
- Related packages: [Layout Builder](../../layout-builder/README.md).
- Focused tests: `vendor/bin/pest packages/api/tests --configuration=phpunit.xml`.

<!-- prettier-ignore-end -->
