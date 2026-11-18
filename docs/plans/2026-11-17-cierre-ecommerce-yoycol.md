# Ecommerce closeout execution

Authorized by Reny on November 17, 2026. Branch: `dos/ecommerce-closeout-20261117`.

Implemented: read-only recovery after refunds (#276); removal of unconfigured physical checkout (#287); shared PayPal finalizer and capture webhook from PR #263; scheduled recovery for every product type; atomic refund persistence; durable receipt/tracking/operator notices; separate sales pause; minimum supplier cost guard; readiness command; compatible dependency updates.

Validation: financial regressions, PHP/JS suites, Vite build, browser purchase/cancel/account path with simulated providers on desktop/mobile. Results are in `docs/qa/ecommerce-closeout-2026-11-17.md`.

Remaining external work: `docs/ecommerce-operations.md` has the release, credentials, commercial approvals, real sandbox and controlled live fulfillment checklist. No production deployment, provider payment or real purchase is claimed. Never activate sales solely because unit tests pass.
