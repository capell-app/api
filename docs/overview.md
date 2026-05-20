# API

Status: **Available, route-only** · Kind: **package** · Tier: **premium** · Bundle: **publishing-pro** · Contexts: **frontend** · Product group: **Capell Publishing Pro**

API exposes published Capell page data as public JSON for external renderers, previews, static build systems, and integrations.

## What This Package Adds

- Public page resolution endpoints at `/api/capell/pages/resolve` and `/api/capell/v1/pages/resolve`.
- JSON responses with `X-Capell-Api-Version` and `X-Capell-Cache-Tags` headers.
- Optional layout graph output through Layout Builder.
- HTML sanitization for public content fields.
- Context signature enforcement for explicit site/language resolution.

## Install Flow

- Composer package: `capell-app/api`
- Hard dependencies: `capell-app/core`, `capell-app/layout-builder`
- Optional dependencies: none declared.
- Run `capell:extension-install capell-app/api` after Composer install so endpoint responses pass the installed-package guard.

## Admin Surfaces

This package adds no Filament resources, pages, widgets, relation managers, or settings screens by itself.

## Frontend Surfaces

- `GET /api/capell/pages/resolve`
- `GET /api/capell/v1/pages/resolve`

Both routes resolve a published page from the request context and can include selected fields, metadata, and bounded layout output.

## Screenshot Plan

- Successful page resolve JSON response.
- Not-found page resolve JSON response.
- Forbidden explicit context response without a valid signature.
- Layout graph JSON response with bounded containers.

## Known Risks

- Route responses are only useful after the package is marked installed and the host app has published pages to resolve.
- Layout output depends on `capell-app/layout-builder`; final captures should install only core baseline, Layout Builder, and API.

## Feature Suggestions

- Add a small admin API diagnostics page showing route status, installed flag, middleware stack, and a sample signed context URL.
- Add response shape fixtures to the docs so frontend consumers can build typed clients without reading controller internals.
- Add an optional endpoint explorer command that resolves a selected site/language/page and writes a redacted JSON example for documentation.
