# Capell API

Capell API exposes published page content as JSON for public integrations such as terms pop-ups, headless fragments, and lightweight client-side content fetches.

## At A Glance

- Package: `capell-app/api`
- Namespace: `Capell\Api\`
- Surfaces: public HTTP JSON endpoints
- Service providers: `packages/api/src/Providers/ApiServiceProvider.php`
- Capell dependencies: `capell-app/core`, `capell-app/layout-builder`

## What It Adds

- Host-scoped JSON delivery for published pages.
- Optional layout payload inclusion for integrations that need structured page content.
- HTML sanitization rules for public API responses.

Start with [Overview](docs/overview.md) for package surfaces and screenshot coverage, then [Page API](docs/page-api.md) for endpoint shape, host-scoped site resolution, fields, layout includes, and HTML sanitization rules.

Screenshots and response captures are generated from [docs/screenshots.json](docs/screenshots.json) during package documentation runs.
