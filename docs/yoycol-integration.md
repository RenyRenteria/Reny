# Yoycol merch integration

Customers choose a design, size/color, quantity and delivery address on the website. A short-lived server quote includes the product price and shipping. PayPal receives the same total, item quantities, shipping breakdown and fixed delivery address. Only a verified completed customer payment becomes eligible for supplier submission.

## Supplier payment limitation

Yoycol's public V4 API documents order creation, cancellation and tracking, but no payment endpoint. Its help center currently limits Auto-Pay to Shopify. This integration therefore creates an unpaid supplier order and exposes `payment_required` in **CMS → Store → Yoycol**. The merchant must find that order in Yoycol and pay production and shipping there. The scheduler reads `paidAt` and tracking afterward. Customer payment and supplier payment are separate states.

Do not describe this integration as unattended fulfillment. No browser automation, undocumented payment endpoint, saved card or reused Slack credential is used to pay Yoycol.

## Activation

1. Deploy this branch using the normal reviewed release process. Run `php artisan migrate --force` and build assets with `npm ci && npm run build`.
2. Rotate the keys previously posted in Slack. Configure fresh `YOYCOL_ACCESS_KEY` and `YOYCOL_SECRET_KEY` through the deployment secret manager, then `YOYCOL_ENABLED=true`. Do not commit them. Rebuild the configuration cache after changing deployment variables.
3. Preserve `APP_KEY`: quote addresses and supplier order payloads are encrypted with Laravel's application key. Use the shared database and cache for all application instances.
4. Ensure Laravel's scheduler runs every minute. The registered schedules are `yoycol:sync` every minute and `yoycol:sync --catalog` hourly. They use overlap locks. No separate queue worker is required.
5. Open **CMS → Store → Yoycol → Sincronizar diseños**, or run `php artisan yoycol:sync --catalog`. New products are drafts with no retail price. Set the title, description and approved USD retail price, then publish each selected product. Sync preserves editorial text and prices.
6. Complete a PayPal sandbox purchase using a test shipping destination before enabling customer sales. Validate the provider order, pay it only with merchant approval, and check production/tracking on both systems. The test suite and browser UAT use simulated payment and supplier writes; no real order has been placed by this implementation task.
7. Review the payment-required and review states in the CMS regularly. PayPal refunds still use the existing verified `/paypal/refund` webhook. Supplier cancellation and customer refund remain separate operations.

SSH is not required to develop or test the integration. Production deployment and secret configuration were not performed in this runtime.

## Catalog and shipping

- Templates provide the design code and variants. Orders use the API's documented direct `skuCode` + `designCode` mapping with `source=productTemplate`; creating a second remote product mapping is unnecessary.
- Only variants with a numeric USD cost are offered. Shoe sizes come from variant attributes, since the API may return `size=null`.
- A read-only account check found two templates: a leather jacket with 21 returned variants (14 priced, seven with null cost), and leather shoes with 14 priced variants. Import excludes the unpriced jacket variants. No account keys or customer data are in the repository.
- Products disappear from sale if unpublished, removed upstream, unpriced, variantless, or not refreshed within 24 hours. A failed import does not mass-unpublish products.
- Checkout accepts one product/variant per order, with quantity 1–10. Mixed bags and local bank-reference checkout are rejected for these products.
- Quotes use `firstPrice + additionalPrice × (quantity - 1)` for the selected SKU and country region. Null/unavailable rates are rejected. Quotes expire after 15 minutes, bind to the browser session and are consumed atomically once. A product/price/design change requires a new quote.
- `config/yoycol.php` lists 17 supported destinations, including Panama, with explicit region mappings verified against Yoycol's published shipping zones. Never infer region membership from internal region-code names: Panama is in **A area**, Mexico in **B area**. Add destinations only after verifying their mapping.
- The API returns region rates, not postal-code-specific landed costs. The customer sees a fixed merchandise + shipping total; this implementation does not add an automatic sales-tax engine. Any provider surcharge or provider tax difference requires merchant review before supplier payment and must not trigger another customer charge. Checkout discloses that recipient import duties/taxes are separate. Review store pricing and destination coverage before publication.
- Shipping estimates are transit estimates and exclude manufacturing time. No production or delivery SLA is guaranteed by the application.

## Reliability and operations

`yoycol_quotes` stores the private address/variant/price snapshot. `yoycol_fulfillments` is a durable outbox linked uniquely to the existing order. The pending outbox is created in the same database transaction as checkout. A supplier API outage never rolls back a successful customer payment.

Before the first supplier POST, an atomic database update stores `attempted_at` and a stable `RENY-…` reference. On timeout/crash, the scheduler searches provider orders by exact reference and never blindly repeats the POST. It scans until an empty page, handles capped page sizes and rejects duplicate matches. Unresolved requests remain in `review`. An administrator may authorize resubmission only after confirming absence in Yoycol; the backend reconciles again before resubmitting. Reads are time bounded to remain within the fulfillment lock lifetime.

Capture initiation is persisted before contacting PayPal, preventing cancellation during an ambiguous charge. If the capture response is lost or local completion fails, the scheduler reads the existing PayPal order and verifies order ID, completed capture, exact amount, USD currency and no reported refunds. It then completes the original order and Royal Pass grant transactionally. It never performs a second capture to recover a response.

Known supplier states:

- `awaiting_capture`: customer payment has not yet been confirmed.
- `submitting`: the first supplier request started; interrupted requests are reconciled.
- `payment_required`: merchant payment in Yoycol is required.
- `production`: the provider reports `paidAt`.
- `shipped`: a tracking number has been returned.
- `review`: ambiguous submission or provider approval requires attention.
- `refund_review`: customer refund recorded; stop further submission and review supplier cancellation.
- `cancelled`: cancelled at the supplier; this does not itself refund PayPal.

Updates normally poll every 15 minutes; initial paid submissions are processed on the next scheduler run. The CMS also offers an explicit state refresh. Customers see a simplified status and carrier/tracking events from their own account; authorization prevents access to another customer's tracking. API secrets, raw supplier payloads and delivery addresses are not returned on that page.

To pause new sales and API processing, set `YOYCOL_ENABLED=false` and rebuild the configuration cache. Keep the tables and encryption key for reconciliation; do not roll back commerce tables after orders exist.

## Official contracts consulted

- [Yoycol API onboarding](https://www.yoycol.com/helpcenter/11/277/How-to-Get-Started-with-the-API)
- [Yoycol V4 documentation](https://www.yoycol.com/api/2025/redoc) and [OpenAPI schema](https://www.yoycol.com/api/2025/v3/api-docs/open-api), version 2025.11, retrieved October 19, 2026 UTC.
- [Auto-Pay limitations](https://www.yoycol.com/helpcenter/10/275/Why-my-Auto-Pay-isn%27t-working)
- [Shipping zones](https://www.yoycol.com/helpcenter/4/228) and [shipping calculation](https://www.yoycol.com/shipping)
- [PayPal fixed shipping address](https://developer.paypal.com/serversdk/php/models/enumerations/experience-context-shipping-preference)
