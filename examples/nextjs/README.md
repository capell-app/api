# Next.js with Capell v1

Start a headless site with published Capell content and the official TypeScript SDK.
The example includes a responsive home page, a complete page directory, readable
article layouts and clear empty, missing and unavailable states. It uses no
preview token or browser-side API credentials.

Use Node 22.18 or later. Enable the API package on a local Capell installation
with seeded, published pages and a site domain matching its HTTP origin. Never
point this example at production for seeding.

1. In `../../sdk`, run `npm ci && npm run build`.
2. In this directory, run `npm ci`.
3. Set `CAPELL_ORIGIN=http://your-local-capell.test` in `.env.local`.
4. Run `npm run dev` and open the printed Next.js URL.

`CAPELL_ORIGIN` belongs to the Capell backend, not the Next.js server. It must be
reachable from the Next.js server process. An optional site-domain path prefix is
supported by the SDK. The frontend itself is served at the Next.js origin root;
this starter does not configure a Next.js `basePath`.

## Explore the example

- `/` renders the real published home page with links to up to five other pages.
- `/?view=directory` loads the complete paginated public page directory. It uses a
  query parameter so an actual CMS slug such as `/directory` remains available.
- Any published path, such as `/about`, resolves its real API content. Directory
  links stay on the Next.js origin. A missing or unpublished page shows a 404.
- Pages without a body show an empty state. A service failure shows a generic
  retry action without exposing exception messages, server paths or credentials.

The header always links to Home and Browse pages. The directory contains page
identities, not the CMS's editorial menu hierarchy. Titles, content and directory
entries come from your installation; no demonstration records are hardcoded.

## Render your content

The server component uses `CapellClient.page()` and the complete paginated
`navigation()` response. Requests revalidate at Capell on each render; Next's
persistent fetch cache is disabled. API requests have a 15-second cancellation
ceiling. A repeated navigation cursor fails instead of keeping the request open.

Only the public sanitised HTML field is rendered as HTML. Structured block
content stays escaped in a labelled JSON view: replace that view with your typed
component map when building your own site. Do not render arbitrary API fields or
unsanitised authoring data with `dangerouslySetInnerHTML`.

HTML is preserved as returned by the API. Relative links and media resolve at the
Next.js origin. For production, arrange your public assets and page links for
that origin, or implement a deliberate asset delivery strategy; this starter does
not proxy media, rewrite HTML or carry private Capell authentication into a
browser. Review that deployment boundary before publishing your frontend.

Customise `app/globals.css` for your design and `app/components` for your content
presentation. The starter uses system fonts and has no external asset dependency.

## Verify against an installed site

Run `npm test` for render, empty-state, link-safety, cursor and error-disclosure
checks, and `npm run typecheck && npm run build` for Next.js compilation.

The browser check uses the monorepo's existing Playwright installation. Provision
that tooling with the repository's normal bootstrap, start this example against
your isolated Capell installation, and choose an existing published page:

```sh
EXAMPLE_ORIGIN=http://127.0.0.1:3000 \
CAPELL_ORIGIN=http://your-local-capell.test \
EXAMPLE_PAGE_PATH=/about \
npm run test:browser
```

The check compares real API titles and HTML with the rendered home/article, opens
the directory and follows a page, checks navigation and overflow at four viewport
widths, then checks a missing page. It does not seed content or start a server.
Keep installation-specific records, credentials and captured images outside the
example's tracked source.
