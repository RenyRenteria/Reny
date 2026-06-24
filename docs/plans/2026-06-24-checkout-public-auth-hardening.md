# Checkout Public Auth Hardening

Issue: https://github.com/RenyRenteria/Reny/issues/147

## Problem

Public checkout currently resolves the submitted email or phone to an existing account, logs that account in, and then uses the account id to find pending PayPal orders. A public buyer can therefore submit another customer's identifier and capture or reuse that identity without proving ownership.

## Approach

- Stop public checkout from calling `Auth::login()` in PayPal order creation, PayPal capture, and local checkout.
- Bind PayPal pending orders to a server-side checkout session token stored as a hash in order metadata.
- Resolve pending PayPal captures by PayPal order id plus the current checkout session hash, not by submitted identifier.
- Create or select a checkout customer only after a valid PayPal capture for public PayPal checkout.
- For public requests, never return an existing user from `RoyalPassService::findOrCreateCustomer()` unless the caller explicitly allows existing accounts.
- Keep authenticated checkout attached to the authenticated user, while public checkout uses a new customer record for new identifiers or a synthetic guest customer when the submitted identifier already belongs to another account.
- Resolve public PayPal products without an authenticated user context; member, Royal, or purchased-only CMS products remain gated and are not exposed through public checkout.
- Return an account redirect URL only when the checkout request is authenticated, so public buyers are not sent to the auth-only account page after payment.

## QA Coverage

- Public PayPal order creation using an existing account email does not authenticate the browser.
- Public PayPal capture with an existing account email completes on a separate guest customer and leaves the existing account untouched.
- Capture from a different session cannot claim another pending PayPal order.
- Public local checkout using an existing account email creates a separate guest customer and does not authenticate the browser.
- Existing guest/new identifier checkout continues to create and complete purchases.
- Authenticated checkout still attaches orders to the signed-in user and receives the account redirect URL.
