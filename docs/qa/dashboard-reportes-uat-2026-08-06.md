# Dashboard report UAT — 2026-08-06

## Environment

- Branch: `dos/dashboard-reportes-227`
- Laravel local server with a fresh SQLite database and deterministic admin fixture
- Business timezone: `America/Panama`
- Browser automation: Playwright Chromium

## Responsive matrix

The report page was loaded at every required width. At each width, `documentElement.scrollWidth`, `body.scrollWidth`, and `window.innerWidth` matched; no document-level horizontal overflow was present.

| Width | Result |
| ---: | :--- |
| 320 px | Pass |
| 375 px | Pass |
| 430 px | Pass |
| 768 px | Pass |
| 1024 px | Pass |
| 1440 px | Pass |

## Functional and accessibility checks

- Preset controls update the selected range. A keyboard interaction issue in the initial radio markup was found during UAT and fixed with explicit input/label associations.
- Selecting the custom preset enables both date fields; submitting `2026-08-01` through `2026-08-06` preserves `preset`, `start`, `end`, and `product_sort` in the URL.
- Reloading the custom URL restores the selected control and dates.
- Arrow-key radio navigation selects another preset and disables the custom fields.
- Current and previous sales series are visible and differentiated by label and line treatment, not color alone.
- The chart exposed 24 keyboard-focusable data points in the fixture.
- USD and EUR stayed in separate KPI totals.
- Funnel, content, products, and shows rendered from their populated fixtures.
- Summary export downloaded as `summary_2026-08-01_2026-08-06.csv`.
- Browser console: 0 errors and 0 warnings during filters, custom range, keyboard navigation, and CSV download.
- A selected range entirely before content instrumentation was rechecked after QA feedback and renders as “No disponible” with the instrumentation date rather than as a true empty result.

## Automated evidence

- PHP: 350 tests / 3,713 assertions passed.
- JavaScript: 15 tests passed.
- Production Vite build passed.
- Pint and `git diff --check` passed.
- Query-bound regression covers a 15-show fixture and caps a complete dashboard render at 20 queries independently of row count; repeated order/refund/event ranges are cached within the request.

## QA integrity follow-up

- Check-ins remain the total canonical check-ins registered in the range. Ticket-to-check-in conversion uses only paid tickets purchased in the selected range and their in-range check-ins, so free RSVP check-ins and tickets purchased in another period cannot inflate the rate.
- CSV cells beginning with spreadsheet formula/control prefixes are neutralized; a public content label of `=2+3` exports as a literal value.
- Orders without a canonical capture timestamp are excluded from period totals and trigger a partial-coverage warning. No historical capture timestamp is synthesized from order creation.
- Video reproductions are emitted from the YouTube `PLAYING` state; music pause/resume does not add another reproduction.

## Remaining environment validation

The issue's p95 target must be confirmed after deployment against a representative staging dataset. Local SQLite timing is not presented as staging evidence.
