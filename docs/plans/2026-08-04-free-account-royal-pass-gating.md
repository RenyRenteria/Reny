# Free Account and Royal Pass Gating

Issue: [#198](https://github.com/RenyRenteria/Reny/issues/198)

## Goal

Ship a freemium access model with no trial:

- guests can browse the public tabs but must register before music playback;
- every authenticated account can play all published music;
- community reading remains available, while community writes require Royal Pass;
- the existing homepage Royal Pass notice becomes the single shared component used by Home, Royals, Videos, Music, Shows, and Store;
- Royal access starts only after the existing paid checkout activates it.

## Access decisions

- `RoyalActive`, `RoyalGrace`, and staff accounts retain Royal access. Keeping grace access preserves the existing renewal and cancellation behavior.
- Music access is based on authentication after publication scheduling has made the item listable. Music-specific `royal` or `purchased` visibility no longer blocks an authenticated listener.
- Guest music requests return `401` with the registration destination. The UI may still show listable release metadata.
- Community likes, comments, live chat messages, polls, club joins, club creation, and club messages all use the same server-side Royal authorization check.
- Reading posts, comments, clubs, and live chat remains available without Royal access.
- Community post creation remains confined to the existing Reny/admin CMS flow.
- No trial implementation exists in the current codebase. Regression assertions will keep trial language and configuration out of checkout.

## Implementation

1. Extract the homepage notice markup into a Blade component that owns its visibility check and reuses the current responsive classes, CTA, checkout destination, and image treatment.
2. Render the component at the top of the five primary tab views and on Home. Remove the separate Store-only banner variant.
3. Change the music access payload so guests are always asked to create an account and authenticated users receive ready access for every published music item.
4. Route post likes and replies through the Royal community write check, align the community view model with that check, and show signup or Royal Pass upsell links based on account state.
5. Update feature tests for the three account states, direct endpoint authorization, shared notice visibility, music playback, and no-trial checkout.

## Verification

- Run focused Laravel feature tests for Home, Store, music, community, entitlement gates, checkout, registration, and account subscription flows.
- Run the complete PHP test suite, JavaScript tests, formatter/lint checks, and the Vite production build.
- Browser-check Home and all five tabs at phone and desktop widths for guest, free, and Royal accounts when a local test environment is available.
- Ask Cuatro for QA notes before the final `reviewer:cuatro` handoff.
