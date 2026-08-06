# Project 4 Analytics Event Taxonomy

Date: 2026-06-16

Reporting extension: 2026-08-06

This taxonomy defines the baseline instrumentation for Project 4 before final provider selection. The browser adapter is provider-neutral:

- Always records events in `window.renyAnalytics.events`.
- Emits debug logs when the URL includes `?analytics_debug=1` or `window.renyAnalyticsDebug = true`.
- Dispatches automatically to `dataLayer`, `gtag`, Plausible, PostHog, or Mixpanel when one is already present.
- Persists the reporting allowlist to the first-party `/analytics/events` endpoint even when no third-party provider is configured.

## Required Payload Fields

Every event should include:

- `schema_version`: currently `1`.
- `session_id`: opaque anonymous browser-session identifier; never an email, phone, user-agent, or IP.
- `event_id`: unique idempotency key generated once per browser event. The server stores it in a hashed `client:` namespace so it cannot collide with canonical server events.
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

Checkout payment events should also include:

- `method`: `paypal`, `card`, `apple_pay`, or `local`.
- `checkout_state`: `unavailable`, `validation_failed`, `payment_started`, `payment_success`, or `payment_failed`.

## Baseline Events

Core:

- `page_view`
- `permission_denied`
- `paywall_cta_clicked`

Music:

- `music_view_all_clicked`
- `music_play_clicked`
- `music_play_ready`
- `music_play_started`
- `music_play_failed`
- `music_access_blocked`
- `music_permission_cta_clicked`
- `music_deluxe_clicked`

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
- `community_club_joined`
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
- `store_rsvp_succeeded`
- `store_rsvp_failed`

Photos:

- `photo_opened`
- `photo_navigated`
- `photo_saved`
- `photo_shared`
- `photo_deep_link_opened`
- `photo_image_failed`

## Reporting Persistence

The first-party reporting allowlist is:

- `page_view` for Store/page visits
- `store_product_opened`
- `store_checkout_started`
- `store_payment_succeeded`
- `store_payment_failed` with a normalized non-sensitive reason code
- `music_play_started`
- `video_play_started`
- `photo_opened`
- `community_note_opened`
- `rsvp_confirmed`
- `ticket_purchased`
- `ticket_checked_in`

The server sets `occurred_at`; `timestamp` from the client is diagnostic metadata only. Duplicate `event_id` values return success without inserting another row. Browser events and the new RSVP/ticket/check-in events do not persist `user_id`. RSVP, ticket purchase, and check-in also emit idempotent server-side events, while dashboard totals continue to use their canonical tables.

`music_play_started` is emitted once per real audio load, not again after pause/resume. `video_play_started` is emitted only after the YouTube Player API reports the `PLAYING` state; loading an iframe is not counted as a reproduction.

## Reporting Sources of Truth

- Captured sales and completed purchases: `orders.completed_at` and the completed/refunded order statuses. Legacy orders without a verifiable `completed_at` are excluded and shown as partial historical coverage; their creation time is never fabricated as capture time.
- Refund deductions: append-only `order_refunds.amount_cents` grouped by `currency` and `refunded_at`. `orders.refunded_at` and `orders.refund_amount_cents` remain compatibility summaries, not the reporting ledger.
- Active Royals and new users: `users`.
- RSVP: `rsvps` and non-purchase RSVP tickets.
- Tickets sold and check-ins: `tickets` joined to captured orders.
- Visits, checkout starts, payment-failure diagnostics, and content consumption: allowlisted `access_events`.

Different currencies are never added together. A later refund subtracts revenue in the refund period without deleting the original completed-order conversion.

## Privacy Contract

Persisted analytics and report exports must never include names, email addresses, phone numbers, payment tokens, provider payloads, raw IP addresses, or full referrer URLs. The first-party endpoint validates an explicit payload allowlist and drops `path`, `title`, and `referrer` before storage. CSV exports contain only aggregate counts, opaque resource keys, content titles, ISO-8601 dates, amounts in major units, and currency codes; formula-leading strings are neutralized before export.

## QA Verification

Manual browser verification:

1. Open any public page with `?analytics_debug=1`.
2. Click visible actions.
3. Confirm `[analytics]` console entries appear.
4. Confirm `window.renyAnalytics.events` contains the events with `screen`, `path`, `item_type`, `item_id`, and `result` where applicable.
5. Confirm the site still works when no analytics provider is configured.
