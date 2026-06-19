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

**For developers:** The package gives developers package-owned service providers, Actions, Data objects, and Laravel routes instead of pushing this behaviour into core or application code.

**For teams:** Versioned public JSON endpoints for published Capell pages and layout graphs, with sanitized output for headless consumers.

## Screens And Workflow

Screenshot contract: `screenshots.json`.

- Successful page resolve JSON response (frontend, required).
- Page resolve layout graph JSON response (frontend, required).
- Page resolve forbidden JSON response (frontend, optional).
- Page resolve not found JSON response (frontend, optional).

## Technical Shape

- Service providers: `Capell\Api\Providers\ApiServiceProvider`.
- Config files: `packages/api/config/capell-api.php`.
- Route files: `packages/api/routes/api.php`.
- Actions: `BuildPublicLayoutPayloadAction`, `BuildPublicPagePayloadAction`.
- Data objects: `PublicPagePayloadOptionsData`.
- Manifest contributions: `health-check: Capell\Api\Health\ApiHealthCheck`, `route: Capell\Api\Manifest\ApiRoutesContribution`.
- Health checks: `Capell\Api\Health\ApiHealthCheck`.
- Cache tags: `api`, `api:pages`.

## Data Model

This package has no schema impact. It does not declare package-owned migrations or required tables.

Docs gap: document extension points here if the package delegates persistence to a host package.

## Install Impact

- Admin navigation: no admin surface declared.
- Permissions: none declared in `capell.json`.
- Public routes: route files exist and must be reviewed before public enablement.
- Database changes: no package migrations declared.
- Settings: no package settings declared.
- Queues or schedules: none detected in standard package paths.
- Cache tags: `api`, `api:pages`.
- Commands: none declared.

## Common Pitfalls

- Review route middleware, throttling, signed URLs, and public-output safety before exposing routes.
- Keep public Blade and cached HTML free of authoring markers, model IDs, permissions, signed editor URLs, and lazy database queries.
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
- [Screenshot contract](screenshots.json)
- [Marketplace assets](assets/marketplace/)
- [Capell content language plan](../../../docs/CONTENT_LANGUAGE_PLAN.md)
- [Capell documentation design system](../../../docs/DESIGN_SYSTEM.md)
- [Capell and package ERD notes](../../../docs/erd/capell-and-package-erds.md)
- Related packages: [Layout Builder](../../layout-builder/README.md).
- Focused tests: `vendor/bin/pest packages/api/tests --configuration=phpunit.xml`.

<!-- prettier-ignore-end -->
