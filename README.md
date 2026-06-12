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

## Boundaries

Capell API only exposes published public content. It must not return drafts, admin-only fields, authoring metadata, signed editor URLs, package internals, permission names, or unsanitized HTML.

Layout payloads are built through package Actions. Public API controllers should not query layout/widget relationships ad hoc or bypass `SanitizesPublicHtml`.

## Runtime Surface

- Provider: `src/Providers/ApiServiceProvider.php`
- Actions: `src/Actions/BuildPublicPagePayloadAction.php`, `src/Actions/BuildPublicLayoutPayloadAction.php`
- Data objects: `src/Data/PublicPagePayloadOptionsData.php`
- HTML safety: `src/Support/SanitizesPublicHtml.php`
- Health check: `src/Health/ApiHealthCheck.php`
- Tests: `packages/api/tests`

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

## Troubleshooting

| Symptom                                  | Likely cause                                                             | Check                                                                                 | Fix                                                                                           |
| ---------------------------------------- | ------------------------------------------------------------------------ | ------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------- |
| JSON endpoint returns no page payload    | The host or path does not resolve to a published page                    | Request the endpoint for a known published page on the expected host                  | Publish the page for that site/locale, then repeat the API request                            |
| Layout data is missing from the response | The request did not ask for layout data or Layout Builder is unavailable | Check the endpoint query/options and confirm `capell-app/layout-builder` is installed | Enable the layout include only for integrations that need it and rerun the package tests      |
| Response exposes unsafe HTML             | A controller or Action bypassed `SanitizesPublicHtml`                    | Run `vendor/bin/pest packages/api/tests --configuration=phpunit.xml`                  | Route public HTML through `BuildPublicPagePayloadAction` or the sanitizer before returning it |
