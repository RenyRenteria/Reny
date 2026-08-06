# Report dashboard QA gate — 2026-08-06

Issue: #227

Verdict: **NO-SHIP pending final browser UAT**. The data-integrity regressions from the first gate are fixed and covered. The required visual/browser matrix still needs a runtime that can open Chromium.

## Passing evidence

- PHP: 345 tests, 3,723 assertions, 0 failures.
- JavaScript: 20 tests, 0 failures.
- Production Vite build: pass.
- Pint and `git diff --check`: pass.
- Fresh SQLite migration chain, including reporting migration: pass.
- Authenticated HTTP smoke with deterministic fixture:
  - custom dashboard request: HTTP 200;
  - summary CSV request: HTTP 200;
  - active range and dates persisted in rendered controls;
  - canonical `USD 125.00`, content, and show fixture values rendered;
  - CSV contained stable headers and no fixture email, provider order ID, or capture ID;
  - 30-request local dashboard p95: 9.7 ms (diagnostic only; not a staging-scale performance claim).
- Query-count regression fixture: 15 shows render with no more than 16 report queries.
- Regression coverage now confirms:
  - an in-range RSVP resolves its canonical show even when the show date is outside the active range;
  - cancellation, unavailable-method, and validation events do not enter failed payments;
  - repeated callbacks reuse a logical analytics event ID within one checkout;
  - video plays are counted only after YouTube reports the `PLAYING` state;
  - custom ranges above 90 days keep current/comparison buckets aligned with no null date point;
  - all six CSV reports map exactly to dashboard values, and sales dates are ISO-8601;
  - submitting a range exposes DOM-backed skeletons and `aria-busy` states.

## Browser blocker

Playwright MCP rejected local navigation before opening the page. The first gate's separate Playwright 1.55 / Chromium 140 bundle under `/private/tmp` also terminated before navigation with:

```text
bootstrap_check_in org.chromium.Chromium.MachPortRendezvousServer: Permission denied (1100)
```

The system Chrome binary failed at the same process boundary. No application console or layout result can be inferred from a browser that never started.

The post-fix attempt was also blocked before navigation because the managed Playwright profile was already in use. No browser claim is made from that attempt.

## Required manual browser gate

Run against this branch at 320, 375, 430, 768, 1024, and 1440 px:

1. Sign into the private CMS report route.
2. Verify there is no page-level horizontal overflow. Internal chart/table scrolling is expected where labeled.
3. Exercise 7/30/90-day, 12-month, and custom filters; reload and confirm query-param persistence.
4. Tab through filter controls, help definitions, chart values, sort links, internal scroll regions, and CSV exports.
5. Confirm current/previous and negative/refund series remain distinguishable without relying only on color.
6. Validate loading, zero, empty, partial-coverage, and module-error/retry states.
7. Download all six CSV reports and compare them with the active UI range.
8. Start a YouTube video and confirm one `video_play_started` event appears only after playback begins, not when the player loads.
9. Cancel PayPal and select an unavailable method; confirm neither action increments failed payments.
10. Confirm browser console and local network requests remain free of errors.

The gate can move to SHIP only after this matrix passes and staging-scale p95 is confirmed at or below 1.5 seconds.
