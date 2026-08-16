# PayPal sandbox E2E runbook

This is a real sandbox checkout gate. It creates PayPal sandbox orders and captures sandbox funds; it never uses live credentials or live buyer accounts.

## 1. Provision PayPal sandbox resources

In the [PayPal Developer Dashboard sandbox](https://developer.paypal.com/tools/sandbox/):

1. Create or select one **Business** sandbox account for Reny.
2. Create a separate **Personal** sandbox account for browser approval. Do not reuse the business account as the buyer.
3. Create a REST app owned by the business sandbox account.
4. Add a webhook whose URL is exactly `https://<sandbox-host>/paypal/webhook`.
5. Subscribe it to:
   - `PAYMENT.CAPTURE.COMPLETED`
   - `PAYMENT.CAPTURE.REFUNDED`
6. Record the client ID, client secret, webhook ID, business email, buyer email, and buyer password directly in the environment secret managers. Do not paste them into Slack, issues, logs, artifacts, or repository files.

PayPal references: [webhook integration](https://developer.paypal.com/api/rest/webhooks/rest/) and [REST idempotency](https://developer.paypal.com/reference/guidelines/idempotency/).

## 2. Configure the dedicated app environment

Deploy the commit under test to a non-production HTTPS host. Configure its secret manager with:

```text
APP_ENV=staging
APP_DEBUG=false
PAYPAL_BASE_URL=https://api-m.sandbox.paypal.com
PAYPAL_CLIENT_ID=<sandbox app client id>
PAYPAL_CLIENT_SECRET=<sandbox app secret>
PAYPAL_WEBHOOK_ID=<sandbox webhook id>
PAYPAL_E2E_ENABLED=true
PAYPAL_E2E_CONTROL_TOKEN=<random 32+ byte secret>
PAYPAL_E2E_REFERENCE_KEY=<random 32+ byte shared HMAC secret>
PAYPAL_E2E_RELEASE_SHA=<exact deployed git commit SHA>
```

Requirements:

- The host must not serve production data.
- The control surface refuses to run unless the app itself uses `https://api-m.sandbox.paypal.com`.
- The webhook URL must be reachable publicly on HTTPS port 443.
- `PAYPAL_E2E_RELEASE_SHA` must be set by the deployment from its immutable revision. The gate refuses evidence when it differs from `GITHUB_SHA`.
- If the app has multiple instances, they must share the configured cache because the one-shot failure and webhook hold use cache state.
- Never enable `PAYPAL_E2E_ENABLED` in production. The code also refuses the control surface when `APP_ENV=production`.
- Clear/reload application configuration after rotating any value.

## 3. Configure the GitHub environment

Create the repository environment `paypal-sandbox-e2e`. Add these **environment variables**:

```text
PAYPAL_E2E_BASE_URL=https://<sandbox-host>
PAYPAL_E2E_EXPECTED_HOST=<sandbox-host>
```

Add these **environment secrets**:

```text
PAYPAL_E2E_CONTROL_TOKEN
PAYPAL_E2E_REFERENCE_KEY
PAYPAL_SANDBOX_BUSINESS_EMAIL
PAYPAL_SANDBOX_BUYER_EMAIL
PAYPAL_SANDBOX_BUYER_PASSWORD
PAYPAL_SANDBOX_CLIENT_ID
PAYPAL_SANDBOX_CLIENT_SECRET
PAYPAL_SANDBOX_WEBHOOK_ID
```

Use the same `PAYPAL_E2E_REFERENCE_KEY` value in the app and GitHub environment so artifact references correlate with sanitized application logs. Use environment protection appropriate to the repository. The workflow has read-only repository permissions and serial concurrency. Do not copy these values to repository-level variables or secrets.

## 4. Run and review

From GitHub Actions, dispatch **PayPal Sandbox E2E**. The workflow performs:

1. strict host, deployed-SHA, account-separation, API-host, webhook-URL, and event-subscription preflight;
2. existing logged-out success with one provider capture and one local activation;
3. explicit cancellation with zero captures and side effects;
4. one-shot post-capture failure into `payment_review`;
5. same-session retry rejected before another PayPal capture;
6. actual signed capture webhook resend and replay with exactly one finalization;
7. full sandbox refunds, signed refund delivery, and refund replay without duplicate local records.

Each scenario gets a new synthetic local account derived from its run reference. Historical orders, refunds, billing records, and access events are retained; the gate never deletes financial history. Fault injection is scoped to that fixture and its PayPal order, so unrelated sandbox traffic cannot consume it or be held.

Run it twice against the same deployed commit before accepting the gate. Save both GitHub run URLs. The uploaded `evidence.json` is intentionally limited to:

- commit and environment host;
- scenario names;
- PayPal order/capture/refund/event HMAC-SHA-256 prefixes;
- provider/local states, HTTP status, and side-effect counts.

The workflow disables Playwright traces, screenshots, and video so the buyer login cannot enter artifacts.

## 5. Inspect sanitized application logs

For each run, confirm the environment logs contain the relevant stages:

```text
create_order
capture_order
persist_capture
webhook_verification
capture_webhook
```

Allowed correlation fields are:

```text
paypal_stage
paypal_endpoint
paypal_http_status
paypal_debug_id
paypal_order_reference
paypal_capture_reference
paypal_event_reference
paypal_result / paypal_issue
```

Reject the evidence if it contains full provider references, email, phone, payer data, request/response bodies, access tokens, client secrets, passwords, or the control token.

## 6. Rotate credentials

1. Create a replacement PayPal REST app secret or sandbox buyer password.
2. Update the dedicated app secret manager and GitHub environment secrets without logging values.
3. For an app rotation, create/verify the new webhook and update both webhook IDs.
4. Reload application configuration.
5. Run preflight plus the full gate twice.
6. Revoke the old client secret/password and remove the old webhook only after both runs pass.

If a run fails after provider capture, do not manually click PayPal again. Inspect the hashed reference and `payment_review` state, restore webhook delivery, and use PayPal event resend/reconciliation.
