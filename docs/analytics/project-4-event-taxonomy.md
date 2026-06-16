# Project 4 Analytics Event Taxonomy

Date: 2026-06-16

This taxonomy defines the baseline instrumentation for Project 4 before final provider selection. The browser adapter is provider-neutral:

- Always records events in `window.renyAnalytics.events`.
- Emits debug logs when the URL includes `?analytics_debug=1` or `window.renyAnalyticsDebug = true`.
- Dispatches automatically to `dataLayer`, `gtag`, Plausible, PostHog, or Mixpanel when one is already present.
- Does nothing network-facing when no provider is configured.

## Required Payload Fields

Every event should include:

- `screen`: stable page or surface key.
- `path`: browser path.
- `result`: `viewed`, `clicked`, `started`, `opened`, `blocked`, `failed`, `succeeded`, or another explicit outcome.

Interaction events should also include:

- `item_type`: content or control type.
- `item_id`: stable content/control key.
- `item_label`: user-visible label when available.

Error or blocked events should include:

- `reason`: failure or blocking reason when available.
- `section`: gated section when relevant.

## Baseline Events

Core:

- `page_view`
- `permission_denied`
- `paywall_cta_clicked`

Music:

- `music_view_all_clicked`
- `music_play_clicked`
- `music_play_started`
- `music_play_failed`
- `music_locked_content_clicked`

Videos:

- `video_view_all_clicked`
- `video_play_clicked`
- `video_play_started`
- `video_external_opened`
- `video_play_failed`

Community:

- `community_note_opened`
- `community_action_clicked`
- `community_like_clicked`
- `community_reply_submitted`
- `community_share_clicked`
- `community_poll_voted`
- `community_club_opened`
- `community_club_created`
- `community_create_club_started`

Auth and account:

- `auth_login_started`
- `auth_register_started`
- `auth_password_recovery_started`
- `account_navigation_clicked`
- `account_viewed`

Store and checkout:

- `store_product_opened`
- `store_product_added`
- `store_filter_selected`
- `store_currency_selected`
- `store_checkout_started`
- `store_payment_method_selected`
- `store_payment_succeeded`
- `store_payment_failed`
- `store_rsvp_started`
- `store_rsvp_confirmed`

Photos:

- `photo_opened`

## QA Verification

Manual browser verification:

1. Open any public page with `?analytics_debug=1`.
2. Click visible actions.
3. Confirm `[analytics]` console entries appear.
4. Confirm `window.renyAnalytics.events` contains the events with `screen`, `path`, `item_type`, `item_id`, and `result` where applicable.
5. Confirm the site still works when no analytics provider is configured.
