# Next.js with Capell v1

Use Node 22.18 or later. Enable the API package on a local Capell installation
with seeded, published pages and a site domain matching its HTTP origin. Never
point this example at production for seeding.

1. Install the dependencies of the SDK package in the sibling sdk directory and build it.
2. Here run `npm ci`.
3. Set `CAPELL_ORIGIN=http://your-local-capell.test` in `.env.local`.
4. Run `npm run dev`, then open the Next.js origin and `/about` (or another
   seeded Capell page path).

The server component loads page content and the complete paginated page directory
through the SDK. Requests revalidate at Capell on each render; Next's persistent
fetch cache is disabled. Only the public sanitised HTML field is rendered as HTML.
Structured block content is displayed as JSON so applications can supply their
own typed component map. Preview tokens are not needed by this public example.
