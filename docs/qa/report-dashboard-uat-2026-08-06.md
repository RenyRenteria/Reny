# Report dashboard QA gate — 2026-08-06

Issue: #227

Verdict: **NO-SHIP pending browser UAT**. Implementation, automated coverage, migrations, build, authorization, CSV privacy, and authenticated HTTP smoke pass. The required visual/browser matrix could not run because this runtime denies Chromium the macOS Mach rendezvous permission before a page opens.

## Passing evidence

- PHP: 341 tests, 3,637 assertions, 0 failures.
- JavaScript: 16 tests, 0 failures.
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

## Browser blocker

Playwright MCP rejected local navigation before opening the page. A separate Playwright 1.55 / Chromium 140 bundle was then installed under `/private/tmp`; Chromium still terminated before navigation with:

```text
bootstrap_check_in org.chromium.Chromium.MachPortRendezvousServer: Permission denied (1100)
```

The system Chrome binary failed at the same process boundary. No application console or layout result can be inferred from a browser that never started.

## Required manual browser gate

Run against this branch at 320, 375, 430, 768, 1024, and 1440 px:

1. Sign into the private CMS report route.
2. Verify there is no page-level horizontal overflow. Internal chart/table scrolling is expected where labeled.
3. Exercise 7/30/90-day, 12-month, and custom filters; reload and confirm query-param persistence.
4. Tab through filter controls, help definitions, chart values, sort links, internal scroll regions, and CSV exports.
5. Confirm current/previous and negative/refund series remain distinguishable without relying only on color.
6. Validate loading, zero, empty, partial-coverage, and module-error/retry states.
7. Download all six CSV reports and compare them with the active UI range.
8. Confirm browser console and local network requests remain free of errors.

The gate can move to SHIP only after this matrix passes and staging-scale p95 is confirmed at or below 1.5 seconds.
