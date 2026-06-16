# P3 CMS PR6 Public CMS Consumption Plan

Date: 2026-06-16

## Scope

- Route Music, Videos, Photos, Store, and Community through a shared public CMS read service.
- Keep the current static Blade payloads as controlled fallback content.
- Cache the last successfully transformed published payload per page and audience scope.
- Enforce `open`, `member`, `royal`, and `purchased` server-side through the editorial visibility model.
- Keep archived content out of listings while preserving direct references with a non-sensitive archived response.

## Validation

- Feature tests for CMS page consumption and fallback cache.
- Feature tests for changing `open` to `member` blocking UI and backend access.
- Feature tests for `purchased` access with expired Royal.
- Feature tests for archived content references.
