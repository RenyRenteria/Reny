# Yoycol browser verification

Local worktree application on `127.0.0.1:8186`, using a separate SQLite database. Screenshots use actual product mockups obtained through read-only Yoycol API calls. Prices of $60/$75 are local UAT values, not approved selling prices.

Verified with Playwright:

- Both imported products appear in Merch and navigate to their size/delivery checkout.
- Shoe variants display actual US/EUR sizes, including sizes exposed only through provider attributes.
- Desktop (1440px) and mobile (375px) render without horizontal overflow.
- Shipping quote displays products, selected shipping service and total separately.
- Switching Standard to Express updates the total; changing quantity invalidates the previous quote and PayPal button.
- Simulated PayPal success exposes the account link. Cancellation clears the consumed quote and allows recalculation.
- A create-order 422 keeps the actionable account-login error even when the SDK wraps it.
- A capture 502 removes payment buttons, keeps address inputs disabled and warns against paying again; a later cancel callback cannot undo capture state.
- CMS login, product price editing, unpublishing and republishing work against the actual local backend. An unpublished product disappears from Merch.
- A locally seeded order shows the supplier-payment alert to the administrator and only “Preparing your order” to the customer; supplier details are not exposed.

Browser quote/payment endpoints and the PayPal SDK were intercepted for deterministic UI verification. These are not real payment or production-order tests. The happy path had zero uncaught JavaScript errors; failure scenarios deliberately returned HTTP errors. Backend tests separately validate API signing, server-owned prices, encrypted addresses, quote expiry/ownership/single use, paid-only submission, ambiguous-response reconciliation, supplier payment/tracking states, refunds, authorization and payment recovery.

Artifacts: `checkout-desktop.png`, `checkout-mobile.png`, `admin-catalog.png`.

Remaining release verification: a real PayPal sandbox purchase, then a merchant-approved Yoycol order/payment and tracking update. Production secrets and final retail prices are not configured by this PR.

Final validation: 443 PHP tests / 4,908 assertions passed; 37 JavaScript tests passed; Vite build, changed-file Pint and `git diff --check` passed.
