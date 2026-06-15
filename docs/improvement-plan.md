# API - Improvement & Growth Plan

> Package: capell-app/api · Kind: package · Tier: premium · Product group: Capell Publishing Pro · Bundle: publishing-pro · Status: Active

## 1. Snapshot

API exposes public JSON delivery for published Capell page data and Layout Builder graphs. It ships versioned resolve routes, middleware/rate-limit config, payload Actions, HTML sanitization, response health checks, route/controller tests, and runner JSON screenshots. The implementation is stronger than the manifest suggests, but `capell.json` repeats the same health check three times, raw JSON is still promoted only through an extension-card marketplace entry, and the public payload sanitizer needs explicit authoring/secret regression coverage.

## 2. Improvements (existing functionality)

1. **Remove duplicate health check manifest entries.** `capell.json` lists `Capell\Api\Health\ApiHealthCheck` three times. Keep one entry and add a manifest test to prevent duplicate health check classes. Evidence: `capell.json`, `tests/Feature/Health/ApiHealthCheckTest.php`. - **S**

2. **Add public payload safety tests for authoring and secrets.** The sanitizer strips unsafe HTML but should be covered against `data-capell-authoring`, signed editor URLs, model IDs, admin paths, bearer tokens, and prompt/secrets inside meta/layout/widget data. Evidence: `src/Actions/BuildPublicPagePayloadAction.php`, `src/Actions/BuildPublicLayoutPayloadAction.php`, `src/Support/SanitizesPublicHtml.php`. - **M**

3. **Clarify v1 versus legacy route behavior.** The package exposes `capell-api.pages.resolve` and `capell-api.v1.pages.resolve`. Docs should identify the v1 route as canonical, note any legacy compatibility path, and define deprecation behavior. Evidence: `routes/api.php`, `docs/page-api.md`. - **S**

4. **Keep raw JSON screenshots out of buyer-facing media until there is a styled explorer.** The package has JSON runner screenshots, but Marketplace media should not imply a UI exists. Add docs/tests that assert raw JSON remains runner evidence only unless a styled endpoint explorer is added. Evidence: `docs/screenshots.json`, `capell.json marketplace.screenshots`. - **S**

## 3. Missing Features (gaps)

Capabilities declared: `public-page-api` and `public-page-api-v1`.

- **No OpenAPI/schema document.** Developers need a stable contract for query params, fields, includes, containers, and response shapes.
- **No ETag/conditional request support.** Public API consumers should be able to cache page payloads efficiently.
- **No explicit error response contract in screenshots.** The runner currently does not promote 403/404 JSON body captures.
- **No signed/private API mode.** This package is public-only; future premium API clients may need token scopes.

## 4. Issues / Risks

1. **Important issue: duplicated health entries can confuse Marketplace/install tooling.** Recommended fix: de-dupe manifest and test. - **P2**

2. **Important risk: public JSON can leak authoring metadata if upstream payloads regress.** Recommended fix: explicit safety tests for page, layout, widget, and meta data. - **P2**

3. **Important docs gap: route versioning is unclear.** Recommended fix: canonical v1 docs and compatibility note for the legacy route. - **P2**

4. **Improvement: JSON screenshots are evidence, not product media.** Recommended fix: hold marketplace media to extension card until endpoint explorer exists. - **P3**

## 5. Marketplace & Positioning

API should be positioned as developer infrastructure for headless/public Capell content delivery. For teams, the value is controlled content reuse. For developers, the value is stable page/layout JSON, sanitized portable HTML, route health diagnostics, and versioned endpoints.

**Current summary:** "Public JSON delivery of published Capell page data."

**Improved summary:** "Versioned public JSON endpoints for Capell pages and layout graphs, with sanitized output and cache-aware delivery for headless consumers."

**Media status:** Keep raw JSON captures as runner evidence. Add styled explorer screenshots only if a first-party endpoint explorer is built.

**Cross-sell:** Agent Delivery for RAG chunks, Layout Builder for layout graph payloads, SEO/Site Discovery for indexing, Frontend Optimizer for cache headers.

## 6. Prioritized Roadmap

| Item                                                     | Bucket | Effort | Impact | Section ref |
| -------------------------------------------------------- | ------ | ------ | ------ | ----------- |
| Remove duplicate health check entries from `capell.json` | Now    | S      | High   | §2.1, §4.1  |
| Add public payload safety tests for authoring/secrets    | Now    | M      | High   | §2.2, §4.2  |
| Document v1 canonical route and legacy compatibility     | Now    | S      | Medium | §2.3, §4.3  |
| Keep JSON screenshots documented as runner evidence only | Now    | S      | Medium | §2.4, §4.4  |
| Add OpenAPI/schema documentation                         | Next   | M      | Medium | §3, §5      |
| Add ETag/conditional request support                     | Next   | M      | Medium | §3          |
| Add non-2xx JSON screenshot runner support               | Next   | M      | Medium | §3          |
| Add optional signed/private API token mode               | Later  | L      | Medium | §3          |
| Build styled endpoint explorer for Marketplace proof     | Later  | L      | Medium | §5          |

## 7. Verification

Plan-writing review only; no commands were run for this package in this pass. First implementation slice should start with:

```bash
vendor/bin/pest packages/api/tests --configuration=phpunit.xml
```

For payload safety changes, include:

```bash
vendor/bin/pest packages/api/tests/Feature/Actions/BuildPublicPagePayloadActionTest.php packages/api/tests/Feature/Http/ResolvePageControllerTest.php --configuration=phpunit.xml
```

## 8. Completion Checklist

- [x] Package plan created from current code, manifest, docs, screenshots, and tests.
- [x] Comprehensive local review pass completed for routes, health, payload Actions, sanitizer, docs, and screenshots.
- [x] Capell audience pass completed for API developers, operators, and buyers.
- [ ] Approved implementation slices shipped.
- [ ] Focused API verification passed.
- [ ] Package tests passed.
- [ ] Repo preflight passed for changed files.
