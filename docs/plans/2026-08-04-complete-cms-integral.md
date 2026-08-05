# Reny CMS integral implementation plan

## Goal

Close issue #202 in one PR by making the existing editorial domain the source of truth for public content, while preserving existing storefront and photo data during migration.

## Workstreams

1. Activate the CMS surfaces
   - Route Content, Editorial, Preview, and Media Library GET requests to their real controllers.
   - Add safe media metadata update, file replacement, and deletion rules.
   - Remove the single-email community editor restriction in favor of existing admin roles.
   - Expose editorial audit history and archive/unpublish actions.

2. Canonical Store and checkout
   - Treat published `EditorialContent` records as the canonical purchasable/event records.
   - Keep storefront settings as layout pointers (`content_id`) and a compatibility fallback only.
   - Validate active state, availability, inventory, price/currency, action type, CTA, and URL before publication.
   - Make public Store cards and checkout read the same normalized amount, currency, date, and availability.
   - Backfill the existing public slots and normalize Festival de la Rosa Dorada to December 16, 2026 at 7:30 PM.

3. Public page parity
   - Render CMS videos without replacing an intentionally empty CMS with the static catalog.
   - Render the active CMS poll and connect it to the existing vote action.
   - Render all published Store products/events plus the selected Album on Store and Home.
   - Keep page HTML and `/api/public-content/{page}` on the same payload contract.

4. Page settings and SEO
   - Reuse `SitePageSetting` for Videos, Photos, Community, and Store header/cover/text settings.
   - Add safe meta description, canonical, Open Graph, and Twitter defaults at page and content level.
   - Add a shared admin page-settings form and public metadata partial.

5. Photos and albums
   - Add album create/update/delete routes and UI.
   - Support cover selection, display order, and explicit photo reassignment before deletion.
   - Preserve photo URLs and reject unsafe album deletion.

6. Preview and verification
   - Add Guest, Member, Royal, and Purchased preview audiences without publishing drafts.
   - Add feature/contract tests for admin CRUD, validation, public payloads, and archive behavior.
   - Run Laravel tests, JS tests, Vite build, and Pint; then complete browser UAT where the local runtime permits it.

## Compatibility rules

- Existing orders continue resolving by `product_key`.
- Existing storefront settings continue rendering while canonical content is backfilled.
- Existing media and photo paths are not replaced or deleted unless the related record is safely migrated or explicitly removed.
- Published content is archived to unpublish; destructive deletion is reserved for drafts or unreferenced assets.

## Verification record

- Laravel integration and contract suite: 309 tests passing with 3,226 assertions, including end-to-end HTTP lifecycle coverage for Video, Poll, Product, Event, page settings, album reassignment, and media replacement/reference safety.
- JavaScript suite: 7 tests passing.
- Vite production build: passing.
- Pint: passing.
- Browser UAT was attempted against an isolated migrated SQLite database on desktop and mobile. This managed runtime could not start or attach Chromium: the shared Playwright MCP profile was already locked, and an isolated browser process was rejected by macOS `MachPortRendezvous` permissions. Cuatro should run the final visual desktop/mobile pass in an unrestricted browser runtime before merge.
