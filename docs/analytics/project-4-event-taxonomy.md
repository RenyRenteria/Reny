# Analytics event taxonomy

Updated: 2026-08-06

The browser adapter remains provider-neutral. It records events in `window.renyAnalytics.events` and dispatches to an already configured `dataLayer`, `gtag`, Plausible, PostHog, or Mixpanel integration. A privacy-reduced subset of business-reporting events is also sent to `/analytics/events`.

## Event envelope

Persisted browser events use schema version 1:

```json
{
  "name": "store_checkout_started",
  "schema_version": 1,
  "event_id": "anonymous UUID",
  "session_id": "anonymous session UUID",
  "timestamp": "2026-08-06T12:30:00.000Z",
  "payload": {
    "screen": "store",
    "path": "/store",
    "item_type": "product",
    "item_id": "opaque-product-key",
    "result": "opened"
  }
}
```

- `event_id` is generated once per browser event and HMAC-hashed by the server as the idempotency key.
- `session_id` lives in `sessionStorage` and is HMAC-hashed by the server before persistence.
- `created_at` is the authoritative server timestamp. `client_occurred_at` is diagnostic only.
- Duplicate `event_id` submissions return success without creating another record.
- Client referrers, payment/order references, raw provider payloads, email, phone, names, IP addresses, and authentication secrets are not persisted.
- The endpoint stores no authenticated user ID for browser analytics events.

## Persisted events

```
Event                         Resource                Reporting use
────────────────────────────  ──────────────────────  ─────────────────────────
page_view                     page                   Store visit
store_product_opened          product                Product visit
store_checkout_started        checkout               Funnel step
store_payment_succeeded       payment                Funnel step
store_payment_failed          payment                Failure diagnostic
music_play_started            music                  Content ranking
video_play_started            video                  Content ranking
free_event_rsvp_succeeded     show                    RSVP instrumentation
store_rsvp_succeeded          show                    RSVP instrumentation
show_check_in_succeeded       show                    Server-side check-in audit
permission_denied             access_gate             Access diagnostics
paywall_triggered_from_photo  photo                   Access diagnostics
```

Payment success/failure allows only a non-sensitive normalized reason code plus method, currency, count, and result. Financial totals never use these analytics events.

## Canonical report sources

- Net sales, completed orders, refunds, currencies, and products: `orders`.
- Active memberships and new users: `users`.
- Ticket sales and check-ins: `tickets` joined to canonical orders.
- Free-event RSVP: `rsvps`.
- Funnel and content interactions: anonymized `access_events` sessions.

`orders.completed_at` is recorded for new captured payments. Historical completed orders that predate that field retain `NULL`; reports fall back to their recorded creation time and explicitly label this as partial historical coverage. No synthetic capture timestamp is backfilled.

## Allowed persisted payload fields

- Common: `screen`, `path`, `result`, `title`.
- Resource: `item_type`, `item_id`, `item_label`, `section`, `source`.
- Commerce: `method`, `checkout_state`, `reason`, `currency`, `item_count`.
- RSVP: `rsvp_status`, `ticket_status`.
- Existing photo access events: `photo_id`, `album_id`.

Unknown events, unknown payload fields, nested payload values, unsupported schema versions, invalid UUIDs, and bodies above 2 KB are rejected.

## Browser QA

1. Open a public page with `?analytics_debug=1`.
2. Exercise Store, checkout, music, video, and RSVP actions.
3. Confirm `[analytics]` console entries include `schema_version`, `event_id`, `session_id`, `screen`, `path`, and explicit results.
4. Confirm persisted rows contain only HMAC session/idempotency keys and allowlisted metadata.
5. Retry an identical event and confirm the row count does not increase.
6. Confirm the public experience still works when no third-party analytics provider is configured.
