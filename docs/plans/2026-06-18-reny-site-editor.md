# Reny Site Editor Plan

Date: 2026-06-18

Source request: replace the overwhelming technical CMS with an editable version of the current public website. Reny should be able to edit headers, covers, songs, videos, galleries, store items, and community content directly on pages that look like the final website, then preview and publish.

## Verdict

Build a new admin experience named `Reny Site Editor` on top of the existing CMS models and public content API. The current admin CMS can remain as a technical back office, but the primary editorial workflow should become page-first, visual, and parity-tested against the public website.

## Goals

- Match the public website page structure for Home, Music, Videos, Photos, Store, and Community inside the admin editor.
- Let editors select a visible page block and edit only the fields that affect that block.
- Support draft, preview, schedule, and publish flows without exposing drafts publicly.
- Reuse `EditorialContent`, media assets, release windows, audit logs, and public CMS payload transformers.
- Make preview show the exact public rendering for the pending draft state.
- Reduce CMS complexity for Reny by hiding technical fields unless the selected block needs them.

## Non-goals

- Do not replace the existing editorial domain tables in this slice.
- Do not remove the technical CMS until the visual editor reaches parity.
- Do not ship a visual-only mock editor that cannot persist real CMS records.
- Do not make checkout, ticketing, or entitlement behavior depend on hardcoded public Blade fallback data.

## Product Model

The editor should expose page-level workspaces:

- Home: hero copy, cover image, featured music, featured video, featured store/event, community highlight.
- Music: page header, featured release, albums, singles, exclusives, cover art, audio metadata, track links.
- Videos: page header, featured video, premieres, performances, behind-the-scenes clips, embeds, thumbnails.
- Photos: page header, galleries, individual photo cards, captions, locations, image assets.
- Store: page header, products, digital drops, events, RSVP versus purchase mode, image assets, price, inventory.
- Community: page header, posts, polls, member prompts, visibility audience, release windows.

Each block needs:

- A public-rendered preview card.
- A clear edit action.
- Save draft, preview, schedule, and publish actions.
- Validation scoped to the content type.
- A status label: draft, scheduled, published, archived, or missing content.

## Architecture

- Add an admin route group for the visual editor, for example `/site-editor`.
- Add a page registry that maps public pages to editable blocks and their content types.
- Reuse `PublicCmsContentService` for read models so editor preview and public payloads stay aligned.
- Add draft-aware preview payloads that can render unpublished edits only for authenticated admins.
- Keep public routes backed by published or scheduled-visible content only.
- Extend the media picker instead of introducing a second upload surface.
- Use the same Blade partials or shared view models for public page blocks and admin preview blocks where practical.

## Implementation Slices

1. Page registry and read-only visual editor shell
   - Add routes, controller, navigation entry, and page tabs.
   - Render Home, Music, Videos, Photos, Store, and Community using current public CMS payloads.
   - Mark missing CMS content explicitly so editors see what still needs to be filled.

2. Block editing and persistence
   - Add block edit panels for headers, covers, songs, videos, galleries, products, events, and posts.
   - Persist through existing `EditorialContentController` validation/workflow where possible.
   - Keep advanced metadata fields hidden behind the selected block context.

3. Draft preview parity
   - Add admin-only preview URLs for full public pages with draft content injected.
   - Ensure preview pages are private and `noindex`.
   - Add desktop and mobile preview checks.

4. Publish and schedule from the page
   - Add block-level publish/schedule actions for admin and artist admin roles.
   - Keep editor role limited to draft preparation.
   - Log all actions in the existing editorial audit trail.

5. Store and event data completion
   - Make store product/event purchase keys, pricing, RSVP mode, and ticketing behavior data-driven or strictly validated against allowed commerce records.
   - Fix RSVP events so they do not enter the PayPal purchase flow.

## QA Acceptance Criteria

- Anonymous users cannot access any site editor route or draft preview.
- Editor role can create and save drafts but cannot publish.
- Admin and artist admin can publish and schedule from the visual editor.
- A published block appears on the matching public page and `/api/public-content/{page}` payload.
- A draft block appears in admin preview but not on the public website.
- Page preview matches the public page layout for desktop and mobile viewports.
- Music validates audio or release metadata before publish.
- Videos validate embed or video asset metadata before publish.
- Photos validate image assets before publish.
- Store validates price, inventory or RSVP mode, purchase key, image asset, and ticketing rules.
- Community validates post or poll requirements and visibility audience.
- Archived content disappears from public listings.
- Existing technical CMS flows still pass regression tests.

## Test Plan

Automated:

```bash
php artisan test tests/Feature/AdminAuthRbacTest.php tests/Feature/AdminEditorialFormsPreviewSchedulingTest.php tests/Feature/AdminMediaLibraryTest.php tests/Feature/PublicCmsContentTest.php tests/Feature/Project3AdminCmsReleaseGateTest.php
```

Add new focused coverage:

- `SiteEditorAccessTest`
- `SiteEditorPageRegistryTest`
- `SiteEditorDraftPreviewTest`
- `SiteEditorPublishWorkflowTest`
- `SiteEditorStoreValidationTest`

Browser UAT:

- Desktop admin site editor: Home, Music, Videos, Photos, Store, Community.
- Mobile admin site editor: content list, edit panel, preview.
- Desktop public pages after publish: `/`, `/videos`, `/photos`, `/store`, `/community`.
- Mobile public pages after publish: same routes.
- Browser console: no errors or warnings during edit, preview, and publish flows.

## Release Gate

No-ship until:

- The visual editor can persist real CMS content for every public page section it exposes.
- Draft preview is private and cannot leak via public payloads.
- Store/event purchase versus RSVP behavior is data-driven or validated.
- The focused site editor suite, current Project 3 CMS suite, full Laravel suite, and asset build pass.
- Browser UAT confirms visual parity across desktop and mobile.

## Open Decisions

- Whether the technical CMS remains visible to Reny after visual editor launch or becomes admin-only fallback.
- Whether Home page hero/header should be modeled as a new page settings record or as typed editorial content.
- Whether YouTube embeds remain metadata-only or move into media assets.
- Whether store products and events should move from editorial content metadata into dedicated commerce tables before the editor ships.
