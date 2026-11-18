# Ecommerce activation and operations

The code changes do not activate production sales. The release operator must complete the concrete configuration and provider checks below. Develop/test without SSH; use the existing Forge deployment console for the reviewed release. Never paste account keys in issues or chat.

## Release checklist

1. Review and merge the ecommerce closeout PR. It includes the PayPal sandbox implementation previously proposed in PR #263, reconciled with current Yoycol code. Do not merge the old PR separately afterward.
2. Deploy through Forge with `composer install --no-dev --prefer-dist --no-interaction`, `npm ci`, `npm run build`, `php artisan migrate --force`, then refresh Laravel config/view caches. Do not change `APP_KEY` or roll back commerce tables. Preserve financial history.
3. Rotate Yoycol keys at the provider, save fresh values in Forge's secrets/environment, and set `YOYCOL_ENABLED=true`, `YOYCOL_SALES_ENABLED=false`. Do not reuse the keys previously exposed in chat. Confirm PayPal environment and rotate any exposed PayPal credentials independently.
4. Import real designs in CMS → Store → Yoycol. Approve each variant, design and retail price. Check highest variant cost, supplier shipping, PayPal fees, taxes and target net margin. Import never automatically publishes or assigns prices. Remove unsupported destinations from `config/yoycol.php` only after business approval; configured shipping-zone mappings are not a tax assessment.
5. Publish approved delivery, return, cancellation and damaged-item terms and set their HTTPS URL in `YOYCOL_POLICY_URL`. Confirm destinations, tax treatment and a human owner for provider payment. Do not promise production or delivery times that the supplier has not confirmed.
6. Configure a real mail transport and sender, `COMMERCE_OPERATIONS_EMAIL`, `COMMERCE_NOTIFICATIONS_SINCE=<ISO-8601 activation timestamp>`, and `COMMERCE_NOTIFICATIONS_ENABLED=true`. No mail is sent when disabled or without an activation date. Historical orders before that date are intentionally excluded and need a separate operator review.
7. Verify Forge runs Laravel's scheduler every minute. The application registers `yoycol:sync` every minute, catalog refresh hourly, and `paypal:reconcile` / `commerce:notify` every five minutes. Use a shared cache when there are multiple instances. Check scheduler logs and actual receipt/operator mailbox delivery.
8. Provision the dedicated GitHub environment and PayPal sandbox accounts described in [the sandbox runbook](qa/paypal-sandbox-e2e.md). Register `/paypal/webhook` for capture-completed and refund events. Verify two consecutive real sandbox workflow runs for the exact deployed SHA. Local mocked tests are not substitutes.
9. Configure final live PayPal values, keep sandbox controls disabled, enable `YOYCOL_SALES_ENABLED` only at the controlled launch, and run `php artisan commerce:readiness` (use `--sandbox` only on sandbox). This checks presence/configuration, not key validity, rotation, policy legality, mailbox delivery or scheduler health.
10. Choose an approved real product, size, destination and maximum spend before the controlled live purchase. Validate charge, order confirmation, supplier order and costs, manual supplier payment, manufacturing and actual tracking. A customer refund does not cancel supplier production. Validate a refund separately on an eligible authorized order.

## Operations

- On a `payment_required` alert, open the CMS, inspect the supplier reference and total, and pay that exact order in Yoycol. The API does not provide automated supplier payment. Watch for `production`, then `shipped` and tracking. A customer payment alone is not supplier payment.
- On `review`, use **Actualizar estado** first. Unknown creation responses are recovered by reference without another POST. Authorize re-submission only after verifying absence at the supplier.
- On `refund_review`, do not pay production. Read-only reconciliation can recover the remote ID after a lost response. Use explicit **Cancelar en Yoycol** when permitted. PayPal refund and supplier cancellation are independent actions.
- On `payment_review`, reconcile through `paypal:reconcile` and provider records. Do not ask the customer to pay again. Investigate pending captures older than 15 minutes; the operator receives reminders for them.
- For shipping inquiries, direct the owner to their authenticated account. Customer emails omit private addresses and raw supplier payloads.
- Pause new sales with `YOYCOL_SALES_ENABLED=false`; keep supplier processing enabled so paid orders and refunds continue to reconcile.

## Outstanding external gates recorded November 17, 2026 (Panama)

GitHub exposes only a `staging` environment; no `paypal-sandbox-e2e` environment or repository PayPal secrets/variables were available. The current GitHub identity has push/triage access but no admin/maintain access. No deployment, secret rotation, real provider purchase/refund/payment or outbound email delivery was performed in this runtime. SSH is disabled. Actual products/prices, approved policies/destinations and the provider-payment owner are awaiting business input.

The npm audit still reports six high dependency entries, all tracing to `picomatch` GHSA-fc5q-39xc-vjpf. The upstream advisory has no patched version at this review; it describes ReDoS from attacker-controlled glob patterns. These are development/build dependencies, not a demonstrated production checkout exploit. Do not run builds with untrusted patterns or expose the Vite development server. Track an upstream compatible fix under #14/#19. Do not force a breaking Vite downgrade and call the issue resolved.
