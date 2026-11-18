# API

<!-- prettier-ignore-start -->

You can show your published Capell pages on a separate website or mobile app, such as one built with Next.js or Nuxt. Your editors keep working in Capell as they do now.

Your developer can ask for one page by its address, list pages by type, search page titles or fetch a directory of pages. Lists arrive twenty at a time by default, up to a hundred, and each list points to the next batch if there is one. Pages that are live today are the only ones returned, and their content is cleaned of anything unsafe on the way out. The access is read-only and adds no admin screen. Drafts stay private unless your developer uses a personal key made for previewing.

When an editor publishes, saves or deletes a page, or a page's dates pass, Capell can send your front end a signed message. Sending starts once your developer has added the front end's address and a secret, and the messages go out as background jobs, which your developer or host keeps running. Each message names the site and page that changed.

## What it does

API gives external systems a public, read-only way to retrieve published Capell page data. It is intended for headless sites and other frontend consumers; it cannot create or edit content. Trusted server-side preview tokens can read sanitised draft content.

## Do I need to do anything?

Usually no. It works behind the scenes once installed and configured. A developer uses the versioned public endpoint when another frontend needs published pages or an approved layout graph.

## Where it shows up

It does not add a day-to-day screen. Its effect appears in connected frontend systems that read published content.

## Good to know

- This package provides supported public delivery and token-scoped preview; new consumers should use the canonical `/api/v1/pages/resolve` endpoint. The unversioned `/api/capell/pages/resolve` path is a compatibility alias.
- Returned HTML and layout data are sanitized, and drafts, authoring state, and private admin data are not part of the public response.
- Ask a developer to review middleware, throttling, and any signed site/language context before exposing a new integration.

---

For developers: see the [README](../README.md).

<!-- prettier-ignore-end -->
