# Community Interactive Experience

Date: 2026-06-19
Issue: #61 Proyecto 4: Convertir Community en experiencia interactiva
Branch: dos/issue-61-community-interactive

## Goal

Turn `/community` from a static preview into a stateful community surface:

- Reny note can open as full content.
- Likes persist with optimistic UI and rollback.
- Replies/comments persist with validation and visible errors.
- Share uses Web Share API with clipboard fallback.
- Poll voting persists and blocks duplicate votes.
- Country clubs have a detail route, join flow, and persisted messages.
- Guests/open accounts see permission gates instead of mutating controls.
- Browser analytics tracks success/failure states for community actions.

## Implementation Slices

1. Add community interaction tables and models for post reactions, post replies, poll votes, country clubs, memberships, and club messages.
2. Add `CommunityInteractionService` to compose page state from CMS/fallback content plus persisted user state.
3. Add `CommunityInteractionController` JSON endpoints and a public club detail route.
4. Update `/community` and club detail Blade views to render real action controls inside existing design patterns.
5. Update `resources/js/app.js` to call endpoints, roll back failed optimistic likes, block double votes client-side after server success, and show errors in `communityToast`.
6. Add feature tests for auth gates, persistence/idempotency, duplicate vote blocking, and club detail rendering.

## Risk Notes

- CMS payload is cached by user, so action state must be composed outside the cached CMS payload.
- Poll CMS stores percentages, not raw counts. Use existing percentages as base counts, then layer persisted votes on top.
- Country clubs can begin from static defaults; database rows are created only when a member joins or creates a custom club.
