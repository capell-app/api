# Supported headless API

The supported `/api/v1` API lets a Next.js, Nuxt or mobile frontend read Capell
content. It includes pages, page resolution by path, collections by public page
type, title search, a page directory for navigation, and images referenced in
public HTML. It is read-only.

The [v1 contract](v1-contract.md) defines pagination, preview abilities, response
shapes, cache behaviour and webhook signatures. The TypeScript SDK (`sdk/`)
and the Next.js example (`examples/nextjs/`) ship alongside the package source.
The SDK is prepared for local distribution; registry publication is separate.

```http
GET /api/v1/pages?limit=20&filter[type]=article
GET /api/v1/collections/article?limit=20
GET /api/v1/pages/resolve?url=/about
GET /api/v1/search?q=Welcome
GET /api/v1/navigation
GET /api/v1/media
```

Continue a list by passing `meta.next_cursor` as `cursor` with the same endpoint,
filters, limit and preview token. Invalid or expired cursors return 422: restart
without a cursor. A page can be short or empty when the bounded eligibility scan
reaches its limit. Continue until `next_cursor` is null. There are no totals.

The API uses the request host and configured site-domain prefix to select the
site/language. Use the actual published Capell origin, not an arbitrary internal
hostname. Explicit `site` and `language` query parameters still require Capell's
signed context URLs. Re-sign these URLs when changing their query, including a cursor; domain-based context is simpler for SDK pagination.

## Server-side preview

Install Sanctum's token migration using its normal installation workflow and add
`Laravel\Sanctum\HasApiTokens` to the host application's user model. Create a
personal token with `api:preview` and `api:preview:site:{site-id}` abilities.
Use `Authorization: Bearer ...` and `preview=1`. An expired, revoked or incorrectly
scoped token returns 403. Session authentication does not grant API preview.

`api:preview:restricted` explicitly grants bypass of anonymous eligibility
contributors and blueprint restrictions; reserve it for trusted editorial tools.
`api:preview:deleted` includes soft-deleted pages, addresses and translations.
Neither ability returns authoring state. Preview responses always have
`Cache-Control: private, no-store` and cannot become conditional 304 responses.
Keep all preview tokens in server-only environment variables.

## Caching and revalidation

Anonymous responses use an eligibility-aware ETag and revalidate on every read.
`Cache-Tag` uses html-cache's `site-{id}` purge keys (and `page-{id}` on the
page resolver). Cloudflare consumes and strips it at the edge; any other
edge forwards it to visitors unless it strips the header, so those site and page
IDs reach visitors behind an edge that does not. `Surrogate-Key` carries the same
keys only when `CAPELL_API_EXPOSE_SURROGATE_KEY` is true; enable it only behind an
edge that strips the header, because most CDNs forward it. All responses from a
site can be invalidated with that site key. `X-Capell-Cache-Tags` is always the
coarse `api` tag. The page resolver keeps its response shape; new integrations use `/api/v1`.

Configure `capell-api.webhooks.endpoints` with stable keys, a `site_id`, an HTTPS
`url`, and an independent secret of at least 32 random characters. Set
`capell-api.webhooks.queue` to a supervised queue. Keep secrets in environment
variables or a secret manager. A removed endpoint or disabled API package revokes pending deliveries.

Page saves, unpublishes, deletions, URL changes and public-read policy withdrawals
queue site-scoped purge notifications after commit. Future publish/expiry dates also queue delayed site purges; use an asynchronous queue driver with workers, because the sync driver cannot honour delays. Retry delivery is at least
once: your receiver must be idempotent. The signed payload carries `id`, `version`,
`event`, `occurred_at`, `paths` and `surrogate_keys`. Site purges cover removed
paths, so `paths` can be empty. There is no draft content in a notification.

Verify HMAC-SHA256 over `X-Capell-Timestamp + '.' + raw_request_body` using the
endpoint secret, compare signatures in constant time, reject timestamps more
than five minutes away, then process the signed payload once with a shared lock-capable cache. The
package's `WebhookSignature::process` authenticates before locking, runs an
idempotent purge callback under a 60-second lease, and remembers completion for
24 hours only after the callback succeeds. Callback failures remain retryable;
a crash releases the lease after 60 seconds. Keep the callback timeout below
60 seconds. A crash after a purge but before recording completion may repeat
the purge, so the callback must be idempotent. Return 2xx for Processed or
Duplicate, a retryable status for Busy, and 403 for Invalid.
Retries retain the signed ID and refresh timestamp/signature. Five attempts use
10, 60, 300 and 900 second backoffs; exhausted deliveries emit a redacted warning.

## Resource boundaries

Navigation is a paginated public page directory, not a serialisation of menu
editor state. Media lists image references already present in sanitised readable
HTML; it does not enumerate the media library, private attachments, or temporary
signed download links. Structured blocks remain sanitised structured content;
frontends supply their own component mapping.
