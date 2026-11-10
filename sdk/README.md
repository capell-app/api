# Capell SDK

Private distribution of the supported v1 client. Build with `npm ci && npm run
build`; run `npm test` and `npm run check`. No registry publication is enabled.
The Next.js example installs this directory using a local file dependency.

```ts
import { CapellClient } from '@capell/sdk'
const capell = new CapellClient({ origin: 'https://content.example.com' })
const { data: page } = await capell.page('/about')
const { data: links } = await capell.navigation()
for await (const item of capell.allPages({ limit: 20 })) console.log(item.url)
```

All requests use `cache: 'no-store'` to avoid framework caches retaining withdrawn
content. A frontend may implement webhook-driven invalidation with the documented
purge keys, but must retain the API's revalidation semantics. Preview tokens must
remain in server-only environment variables. The client refuses redirects so a
misconfigured content host cannot forward a preview bearer token elsewhere.

Fixture tests use responses exported by the real PHP API tests, recorded by the maintainers' fixture-recording mode; do not hand-edit fixtures. Navigation v1 is a
paginated page directory; media is a paginated projection of public HTML images,
not a list of all uploaded assets. Empty media pages can still have a next cursor.
