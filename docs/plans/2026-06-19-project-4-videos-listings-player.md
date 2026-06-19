# Project 4 Videos Listings And Player

## Goal

Make every visible `/videos` action resolve to a real user flow:

- `VIEW ALL` opens a filtered category listing.
- `Play` opens a controlled internal video player with visible error state.
- External YouTube links are tracked.
- Empty or locked sections render visible states instead of dead controls.

## Implementation

1. Add named `/videos` routes and category query handling without changing the public CMS payload contract.
2. Normalize video section metadata in the Blade view so fallback and CMS videos share category, title, and external URL fields.
3. Replace inline card iframe loading with a shared video modal that tracks clicked, started, failed, and external opened events.
4. Convert `VIEW ALL` buttons to category filter links and render active filtered listings with empty states.
5. Cover the fallback UI and CMS category behavior in feature tests.

## Verification

- Run focused feature tests for `/videos` and public CMS video payloads.
- Build Vite assets if dependencies are available.
- Smoke-check `/videos` in browser if the Laravel app can boot locally.
