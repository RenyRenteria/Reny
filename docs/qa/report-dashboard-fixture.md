# Report dashboard deterministic QA fixture

Reference date: 2026-08-06, `America/Panama`

Active range: 2026-08-01 through 2026-08-06. Previous equal-duration range: 2026-07-26 through 2026-07-31.

## Canonical commerce records

```
Record             Currency  Completed   Amount  Refunded   Status
─────────────────  ────────  ──────────  ──────  ─────────  ─────────
USD current        USD       Aug 4       100.00  —          completed
USD same-period    USD       Aug 3        50.00  Aug 5      refunded
USD old refund     USD       Jul 10       20.00  Aug 4      refunded
EUR current        EUR       Aug 2        40.00  —          completed
USD previous       USD       Jul 28       40.00  —          completed
Failed payment     USD       Aug 4       999.00  —          failed
Pending payment    USD       Aug 4       888.00  —          pending
```

Expected KPIs:

- USD net sales: `100 + 50 - 50 - 20 = USD 80.00`; previous `USD 40.00`; change `+USD 40.00 / +100%`.
- EUR net sales: `EUR 40.00`; previous `EUR 0.00`; percentage `N/A`.
- Completed orders: `3`; previous `1`. The refunded order remains an original conversion.
- Failed and pending payments affect neither totals nor order conversion.
- Currencies remain separate; `USD 80 + EUR 40` must never appear as one total.

## Funnel records

```
Step                 Events  Anonymous sessions
───────────────────  ──────  ──────────────────
Store/product visit       3                   2
Checkout started          2                   1
Purchase completed        2                   1
Payment failed            1                   1
Payment canceled          1                   1
Method unavailable        1                   1
Validation failed         1                   1
```

Expected conversion: visit → checkout `50%`; checkout → purchase `100%`. Repeated events within one anonymous session do not inflate the primary session metric. Canceled, unavailable, and validation events remain diagnostics and do not increase the failed-payment row.

## Show records

For `Panama Live` in the active range:

- 2 canonical ticket RSVP records plus 1 free RSVP = 3 confirmed RSVP.
- 1 ticket connected to a completed order.
- 1 checked-in ticket.
- RSVP → ticket = `33.3%`; ticket → check-in = `100%`.

A free RSVP event with no canonical show/ticket connection must show tickets and check-ins as `N/A` / `Not available`, not zero.

An RSVP created in the active range for a canonical show outside the range must retain the canonical title, date, ticket count, and check-in availability. It must not fall back to an unlinked row.

## CSV privacy checks

Every export must use ISO-8601 dates, major currency units plus a currency column, and the same active filter as the UI. It must not contain customer/admin email, name, phone, holder name, provider order/capture ID, session UUID, HMAC keys, IP, or provider payloads.
