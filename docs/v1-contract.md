# Supported headless API v1 contract

The canonical read API is `/api/v1`. Existing `/api/capell/pages/resolve`
and `/api/capell/v1/pages/resolve` remain compatible aliases; they are deprecated
in documentation without a removal date. Breaking wire changes require a new
major path. Additive optional fields are compatible. A removal requires a
published migration guide and at least twelve months of notice.

## Resources and Data boundaries

- `GET /pages`: cursor-paginated page representations.
- `GET /pages/resolve?url=/about`: one page by its site-relative path.
- `GET /collections/{type}`: pages whose blueprint has the public type key.
- `GET /navigation`: a paginated page directory of public navigation links (not an authoring menu dump).
- `GET /media`: images referenced in sanitised readable HTML pages; private attachments are not enumerated.
- `GET /search?q=term`: cursor-paginated page search.

Every response has a Data boundary: page (`url`, `title`, sanitised `content`),
link (`title`, `url`), media (`url`, `alt`, `mime_type`), resource envelope
(`data`), list envelope (`data`, `meta.next_cursor`), error (`message`).
No Eloquent serialisation crosses this boundary. Preview returns the same safe
representation, never editor metadata, selectors or signed authoring links.

The request host and URL prefix resolve the site and language using Core's
existing domain resolver. Explicit `site` and `language` retain the existing
signed-context requirement. Resource identifiers in JSON are public URLs or
public type keys, never database primary keys.

## Authentication and preview

Public reads need no token. Preview is opt-in (`preview=1`) and requires a real
Sanctum personal access token, not session authentication or Sanctum's transient
SPA token. It needs both `api:preview` and `api:preview:site:{site-id}`. The
site-specific ability prevents a token intended for one tenant previewing another.
Additional abilities gate restricted (`api:preview:restricted`) and deleted
(`api:preview:deleted`) content. Basic preview bypasses publication timing; the restricted ability explicitly bypasses anonymous contributor and blueprint restrictions.
responses still use the public sanitiser. No preview response or authentication
error may be stored, including conditional requests.

## Pagination and filters

Lists use ascending immutable URL-row key order with an opaque authenticated,
encrypted cursor. The cursor is bound to endpoint, site, language, filters,
preview identity and eligibility revision, and expires after one hour. Invalid,
expired or mismatched cursors return 422. Public-read eligibility is rechecked on
every page. No totals reveal restricted records. Limit defaults to 20, maximum
100 source pages. Media pagination operates on these source-page batches; a page can contribute multiple image records. Filtering is exact equality through `filter[type]` and `filter[path]` only;
unknown fields/operators return 422. Search accepts a bounded `q` string and
matches public titles. No arbitrary SQL column or ordering input is accepted.

## Cache and purge model

Reuse Core's `PublicReadProjectionHeaders`: anonymous responses must revalidate
(`public, no-cache`); unknown eligibility state is private/no-store. ETags include
the eligibility stamp and the actual serialised response bytes. Preview, errors
and token-bearing requests use private/no-store and never return 304.
`Cache-Tag` carries html-cache's `site-{id}` and `page-{id}` purge vocabulary
for compatible edge caches; it reaches visitors unless the edge strips it.
`Surrogate-Key` carries the same tags only when `CAPELL_API_EXPOSE_SURROGATE_KEY`
is true. These operational headers are documented identifiers, not content
fields. No positive freshness
window survives an unpublish or scheduled transition. Legacy cache tag headers
remain compatible.

## Revalidation webhooks

Subscribe to Core's page saved/deleted and URL-changed lifecycle, after transaction commit. Register a public-read withdrawal step for policy and timed eligibility changes, purging the affected site (or every configured site for a global change).
Configured endpoints receive queued notifications for publish/update, unpublish
and delete. Each endpoint has its own secret, URL and site scope. Payload Data:
`id` (UUID), `version` (`v1`), `event`, `occurred_at`, public page paths and purge
keys. No draft content, model snapshot or secret is queued or logged.

Sign the exact request bytes as HMAC-SHA256 over `timestamp + '.' + body`.
Send `X-Capell-Timestamp`, `X-Capell-Signature` (`v1=hex`) and delivery UUID.
Receivers must compare in constant time, reject timestamps outside five minutes,
and retain successful delivery IDs for at least 24 hours. Verify the
signature before taking a processing lock; record completion only after an idempotent purge succeeds. Failed processing must release its claim so retries recover. A crashed worker releases its 60-second lease automatically. Retries keep the delivery UUID, use a fresh
timestamp/signature, and are idempotent at the receiver. Five attempts use
backoffs of 10, 60, 300 and 900 seconds; failed delivery is reported without
request bodies or credentials. Requests have bounded connection/total timeouts,
no redirects, and configured HTTPS endpoints only. Endpoint secrets are looked
up at delivery time, so they never enter queue payloads.

## SDK and example distribution

Ship a private-by-default TypeScript SDK source/build package inside this
Composer package, following the repository's package-local JavaScript convention.
The Next.js example consumes it locally. No registry publication is part of this
change. SDK fixture tests consume responses recorded by the PHP integration
suite; the example proves pages and navigation against a seeded local runtime.
