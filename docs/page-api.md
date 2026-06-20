# Page API

Capell API adds a public, read-only page resolver endpoint:

```http
GET /api/capell/v1/pages/resolve?url=/terms
```

The versioned route name is `capell-api.v1.pages.resolve`. The legacy route
`/api/capell/pages/resolve` remains available as `capell-api.pages.resolve`.
Both routes are declared in `capell.json` as public, read-only API endpoints
under the package route contribution. Marketplace and install tooling should
treat the v1 route as canonical and the legacy route as compatibility only.

The machine-readable contract is maintained in [`openapi.yaml`](openapi.yaml).
Keep that document aligned with `capell.json`, this guide, and the controller
response headers whenever the public API changes.

All responses include:

| Header                 | Value    | Notes                                                  |
| ---------------------- | -------- | ------------------------------------------------------ |
| `X-Capell-Api-Version` | `v1`     | Explicit public response contract version.             |
| `X-Capell-Cache-Tags`  | `api`    | Coarse cache tag for downstream API integrations.      |
| `ETag`                 | `"hash"` | Strong validator for the selected sanitized JSON body. |

## Default Response

By default the endpoint returns only the URL, title, and content from the published page translation.

```json
{
    "data": {
        "url": "/terms",
        "title": "Terms and Conditions",
        "content": "<p>Terms content...</p>"
    }
}
```

Missing site, language, or page resolution returns:

```json
{
    "message": "Page not found"
}
```

with HTTP `404`.

## Query Parameters

| Parameter    | Example              | Notes                                                                                                      |
| ------------ | -------------------- | ---------------------------------------------------------------------------------------------------------- |
| `url`        | `/terms`             | Page URL to resolve. Blank or omitted resolves `/`.                                                        |
| `site`       | `1`                  | Optional site id for signed integration URLs only. Unsigned requests resolve from the current host/domain. |
| `language`   | `en`                 | Optional language id, code, or locale for signed integration URLs only.                                    |
| `fields`     | `title,content,meta` | Optional projection. Defaults to `url,title,content`. Unknown fields are ignored.                          |
| `include`    | `layout`             | Optional includes. Supports `layout` and `layout.html`.                                                    |
| `containers` | `main,sidebar`       | Optional layout container filter. Empty, `all`, or `*` returns all containers.                             |

Supported fields are:

- `url`
- `title`
- `content`
- `meta`

## Response Schema

Successful responses always use a top-level `data` object. The default v1 page
schema is:

| Path           | Type         | Notes                                      |
| -------------- | ------------ | ------------------------------------------ |
| `data.url`     | string       | Published page URL.                        |
| `data.title`   | string/null  | Published translation title.               |
| `data.content` | string/null  | Sanitized public HTML content.             |
| `data.meta`    | object/array | Only returned when requested with fields.  |
| `data.layout`  | object       | Only returned when requested with include. |

Error responses use a top-level `message` string and still include the API
version header.

The OpenAPI document defines the same `PageResponse`, `PageData`, `LayoutGraph`,
`LayoutContainer`, `Widget`, and `ErrorResponse` schemas for code
generation and contract tests.

## Conditional Requests

Successful page responses include an `ETag` computed from the selected sanitized
JSON payload. Send that value back with `If-None-Match` to avoid downloading an
unchanged response body:

```http
GET /api/capell/v1/pages/resolve?url=/terms
If-None-Match: "previous-response-etag"
```

When the payload still matches, the endpoint returns HTTP `304` with the same
API version, cache tag, and ETag headers and no JSON body. Different field,
include, or container selections produce a different ETag because the payload
shape changes.

## Site Resolution

Unsigned requests resolve the site from the request host/domain using Capell site domain records. This keeps the public API aligned with frontend multi-site boundaries.

Explicit `site` or `language` selection is only accepted on a signed route URL. The signature fixes the selected `site` and `language`; consumers may still vary `url`, `fields`, `include`, and `containers`.

## Layout Builder Content

Layout output is off by default. Request it explicitly:

```http
GET /api/capell/pages/resolve?url=/terms&include=layout
```

This adds `data.layout` with layout key, containers, and public widget data. Layout and container `meta` are present as empty objects by default; package resolvers should expose only fields that are intentionally public.

```json
{
    "data": {
        "url": "/terms",
        "title": "Terms and Conditions",
        "content": "<p>Terms content...</p>",
        "layout": {
            "key": "default",
            "meta": {},
            "containers": [
                {
                    "key": "main",
                    "meta": {},
                    "layout_widgets": [
                        {
                            "key": "page-content",
                            "occurrence": 1,
                            "type": "content",
                            "data": {
                                "title": "Page content",
                                "content": "<p>...</p>"
                            }
                        }
                    ]
                }
            ]
        }
    }
}
```

Limit output to selected containers:

```http
GET /api/capell/pages/resolve?url=/terms&include=layout&containers=main
```

Use `include=layout.html` when a registered public layout widget payload resolver supports rendered HTML:

```http
GET /api/capell/pages/resolve?url=/terms&include=layout.html
```

HTML remains off unless `layout.html` is requested.

## Sanitization

The API sanitizes returned HTML strings with Symfony's HTML sanitizer using safe blocks, relative links, and relative media. This removes unsafe executable markup such as scripts, inline event handlers, `javascript:` URL attributes, and `srcdoc` payloads while keeping normal author HTML.

Sanitization applies recursively to selected page fields, page meta, layout meta, container meta, widget data, and widget HTML.

This is a response safety boundary, not an editor validation layer. Do not use the API as a sanitizer for saving content.

## Middleware Configuration

The package ships public by default. Apps can add route middleware without
forking the package routes:

```php
// config/capell-api.php
return [
    'middleware' => ['api'],
    'public_pages' => [
        'auth_middleware' => null,
        'rate_limit_middleware' => 'throttle:capell-api',
        'middleware' => [],
    ],
];
```

Use `auth_middleware` only for private/internal consumers. Keep public delivery
unauthenticated when the endpoint powers headless public pages.

## Core Extension Points

The endpoint delegates page resolution to `Capell\Core\Actions\ResolvePublicPageByUrlAction`.

Layout output is built by `Capell\LayoutBuilder\Actions\BuildPublicLayoutGraphAction`.

To customize widget API payloads, bind `Capell\LayoutBuilder\Contracts\PublicLayoutWidgetPayloadResolver` to your own implementation. The default resolver returns widget title and content only, and returns `null` for HTML.
