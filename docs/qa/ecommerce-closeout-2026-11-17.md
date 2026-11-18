# Ecommerce closeout verification

Executed November 17, 2026, Panama time, on `dos/ecommerce-closeout-20261117`, based on main `7c03de2`. This is implementation and local verification, not a production release approval.

## Results

- PHPUnit: 490 passed, 5,446 assertions. Includes all 21 original refund audit regressions, shared capture/webhook finalization, signed-webhook replay, general multi-item payment recovery, disabled-supplier recovery, sales pause, price floor, notification deduplication/retry and atomic refund rollback.
- JavaScript: 38 passed.
- Vite build: passed.
- Composer validation, Pint and diff whitespace validation: passed.
- Composer audit: zero advisories after compatible updates.
- npm audit: reduced from 13 affected dependency entries (including one critical) to six high entries, all downstream of the unpatched `picomatch` advisory GHSA-fc5q-39xc-vjpf. The critical shell-quote issue is removed. Remaining findings are explicitly open.
- Real PayPal sandbox test suite parses/lists three scenarios. It was not executed against PayPal because the dedicated environment and credentials are not provisioned.

## Browser verification

Playwright MCP was available in this execution. Local PHP app, isolated SQLite database and synthetic `Merch QA · Prueba local` fixture. Provider HTTP calls were intercepted server-side with `Http::fake` and all unmocked requests rejected. The merch PayPal SDK button was simulated in-browser, while quote/create/capture requests used the real application endpoints and database.

- Public `/merch`: legacy `data-buy="merch"` absent; published Yoycol product links to dedicated size checkout.
- Desktop 1280 px and mobile 390 px: address and variant inputs, USD 60 product + USD 8 shipping = USD 68, capture, confirmation and account link passed without horizontal overflow. Mobile confirmation showed the new `RENY-3` reference.
- New guest checkout authenticated its own account; the order appeared under `/account`.
- Mobile cancellation after order creation: pending payment cancelled, old quote hidden, controls unlocked; new quote and subsequent purchase succeeded.
- Generic ticket modal: 374 px wide at a 390 px viewport and 900 px wide on desktop; no horizontal overflow, focus moved into the dialog, Escape closed it and returned focus to the initiating product button.
- Browser verification also found checkout analytics rejected display labels such as `Presale [Limited]` with HTTP 422. Item types are now normalized before persistence; the same event returned HTTP 201 after the fix.
- The generic modal's initial attempt to load the actual PayPal SDK with the intentionally fake client ID produced the expected invalid-client/network errors. This does not verify real PayPal button rendering or buyer approval. The real sandbox gate remains mandatory.

No production write, real charge, refund, supplier order/payment, or outbound customer/operator email was performed. Mail delivery was verified with fakes, including retry after transport failure. No manufacturing/tracking SLA can be certified locally.

## External prerequisites

See [activation and operations](../ecommerce-operations.md). Awaiting: rotated provider secrets and deployment; real products/prices/destinations and policy approvals; named operator and configured/verified mail; dedicated sandbox environment/accounts and two passing real runs; authorized live order, separate refund check, manufacturing and actual tracking.

The connected GitHub identity can push but cannot administer environments; only `staging` was listed. No SSH or provider-account administration was attempted. Sales default to paused until activation is deliberate.
