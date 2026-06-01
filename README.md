# Capell API

Capell API exposes published page content as JSON for public integrations such as terms pop-ups, headless fragments, and lightweight client-side content fetches.

## At A Glance

- Package: `capell-app/api`
- Namespace: `Capell\Api\`
- Surfaces: public HTTP JSON endpoints
- Service providers: `packages/api/src/Providers/ApiServiceProvider.php`
- Capell dependencies: `capell-app/core`, `capell-app/layout-builder`

## Why It Helps Your Capell Workflow

- Exposes published Capell page data as public JSON for headless fragments, popups, terms content, and lightweight integrations.
- Keeps API output scoped to published content so integrations do not need admin access or internal page models.
- Gives developers a stable public delivery surface while preserving the rule that authoring state never appears in public responses.

## Best Used With

- [Layout Builder](../layout-builder/README.md)
- [Frontend Authoring](../frontend-authoring/README.md)
- [HTML Cache](../html-cache/README.md)

## What It Adds

- Host-scoped JSON delivery for published pages.
- Optional layout payload inclusion for integrations that need structured page content.
- HTML sanitization rules for public API responses.

## Docs

- [docs index](docs/README.md)
- [overview.md](docs/overview.md)
- [page-api.md](docs/page-api.md)
- [screenshots.json](docs/screenshots.json)

Start with [Overview](docs/overview.md) for package surfaces and screenshot coverage, then [Page API](docs/page-api.md) for endpoint shape, host-scoped site resolution, fields, layout includes, and HTML sanitization rules.

Screenshots and response captures are generated from [docs/screenshots.json](docs/screenshots.json) during package documentation runs.

## Testing

Run package tests from the repository root:

```bash
vendor/bin/pest packages/api/tests --configuration=phpunit.xml
```
