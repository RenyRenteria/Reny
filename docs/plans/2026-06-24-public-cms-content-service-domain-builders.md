# Public CMS Content Service Domain Builders

Issue: https://github.com/RenyRenteria/Reny/issues/151

## Goal

Split `PublicCmsContentService` into domain payload builders while keeping the existing public service contract used by controllers, views, cache invalidation hooks, and playback endpoints.

## Implementation Plan

1. Keep `PublicCmsContentService` as the public facade for:
   - page cache and fallback behavior
   - public methods: `home`, `music`, `musicCollection`, `musicPlayback`, `videos`, `photos`, `store`, `community`, `payload`, `albumDetail`
   - static cache invalidation helpers
2. Add public CMS support classes under `App\Services\PublicCms`:
   - `ContentQuery` for visible/listable CMS queries and music type rules
   - `PayloadMediaResolver` for metadata, media, audio, tracklist, playlist labels, YouTube IDs, and availability helpers
3. Add builders by domain:
   - `HomePayloadBuilder`
   - `MusicPayloadBuilder`
   - `VideoPayloadBuilder`
   - `PlaybackQueueBuilder`
   - `AccessPayloadBuilder`
   - plus small `PhotoPayloadBuilder`, `StorePayloadBuilder`, and `CommunityPayloadBuilder` so page-specific payload shaping leaves the facade
4. Add/adjust feature tests to freeze critical payload shape and values for:
   - home album/single payloads
   - music collections
   - video grouped payloads
   - playback queue order and access states
5. Run focused tests first, then `php artisan test`. Run `npm run build` only if JS/assets change.

## Risks

- Access labels/states are consumed by Blade and JS, so `state`, `access_state`, `access_label`, `cta_*`, and URLs must remain stable.
- Playback queue ordering is user-visible in the modal and covered by existing tests.
- Cache fallback keys depend on access fingerprinting; moving that logic must preserve guest/user separation and unlock invalidation.
