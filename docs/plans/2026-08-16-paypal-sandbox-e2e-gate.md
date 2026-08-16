# PayPal sandbox E2E gate

## Objective

Create an opt-in, repeatable real-PayPal sandbox gate for checkout without making sandbox availability a dependency of pull-request or hotfix CI.

The gate must prove:

- logged-out checkout for an existing Reny account;
- PayPal create, buyer approval, capture, local persistence, and Royal Pass activation;
- explicit buyer cancellation without a capture or entitlement;
- safe `payment_review` state after a post-capture local failure;
- no second capture when the browser retries;
- signed `PAYMENT.CAPTURE.COMPLETED` recovery and replay without duplicate side effects;
- correlation by stage, endpoint template, HTTP status, PayPal debug ID, and hashed provider references only.

## Architecture

1. `PayPalCheckoutFinalizer` owns the local exactly-once transaction. The browser capture callback and capture-completed webhook share it.
2. `/paypal/webhook` verifies PayPal signatures before dispatching supported events. Capture completion reconciles `pending` or `payment_review`; refund handling remains available through the new endpoint and the legacy `/paypal/refund` route.
3. PayPal API success/failure logs use endpoint templates and 16-character SHA-256 reference prefixes. Raw order/capture/event IDs, payer data, request bodies, credentials, and PII are excluded.
4. A disabled-by-default sandbox control surface prepares one synthetic existing account, arms a one-shot post-capture persistence failure, temporarily holds capture reconciliation, releases it, and returns only redacted state counts.
5. Playwright runs serially against a dedicated HTTPS deployment and a personal sandbox buyer. It never records screenshots, video, or traces of PayPal authentication.
6. `.github/workflows/paypal-sandbox-e2e.yml` is manual, environment-scoped, non-required, concurrency one, and independent of normal CI.

## Safety boundaries

- `PAYPAL_E2E_ENABLED` defaults to false and the control surface returns 404 in production or when the app API host is not the PayPal sandbox host.
- Control requests require a dedicated bearer secret and remain rate-limited.
- The workflow accepts no caller-supplied URL. Base URL and exact expected host come from protected environment variables.
- The PayPal API host is fixed to `https://api-m.sandbox.paypal.com`.
- Business and buyer sandbox accounts must differ.
- Browser retries are disabled because a generic test retry could replay a payment flow.
- Evidence includes hashes and counts only and is retained for seven days.

## Validation

- Focused PHP tests for finalization, capture webhook signature/replay/idempotency, sandbox-control isolation, existing checkout regressions, and refund regressions.
- Existing JavaScript tests and Vite build.
- Pint and `git diff --check`.
- Two consecutive manual real-sandbox runs against the same deployed commit after environment provisioning.

## Rollout

1. Merge and deploy to the dedicated sandbox environment with the control surface and PayPal sandbox configuration.
2. Provision the PayPal app, distinct business/personal accounts, and capture/refund webhook.
3. Populate the GitHub `paypal-sandbox-e2e` environment according to the runbook.
4. Run the gate twice against the same commit and attach both run URLs to the issue/PR.
5. Keep the workflow manual until multiple stable runs establish whether a schedule is useful. Never add it as a required PR check.

## Rollback

Disable `PAYPAL_E2E_ENABLED`, revoke the control token, and remove the GitHub environment secrets. The checkout finalizer and signed capture webhook remain safe production recovery behavior independent of the test harness.
