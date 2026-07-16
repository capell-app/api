## What it does

API gives external systems a public, read-only way to retrieve published Capell page data. It is intended for headless sites and other frontend consumers; it cannot create, edit, or expose draft content through this package.

## Do I need to do anything?

Usually no. It works behind the scenes once installed and configured. A developer uses the versioned public endpoint when another frontend needs published pages or an approved layout graph.

## Where it shows up

It does not add a day-to-day screen. Its effect appears in connected frontend systems that read published content.

## Good to know

- This package exposes public delivery only; new consumers should use the canonical `/api/capell/v1/pages/resolve` endpoint. The unversioned `/api/capell/pages/resolve` path is a compatibility alias.
- Returned HTML and layout data are sanitized, and drafts, authoring state, and private admin data are not part of the public response.
- Ask a developer to review middleware, throttling, and any signed site/language context before exposing a new integration.
