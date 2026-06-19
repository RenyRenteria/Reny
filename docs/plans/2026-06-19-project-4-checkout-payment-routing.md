# Project 4 Checkout Payment Routing

## Goal

Complete the `/store` checkout so every visible payment method has a real, non-misleading flow.

## Scope

- Keep PayPal as the only configured capturable provider.
- Add a Local transfer/manual-reference path that validates receipt references and creates pending orders without granting access.
- Make Card and Apple Pay visibly unavailable until a provider contract is configured.
- Validate checkout identity as email or phone on frontend and backend.
- Disable payment controls when the bag is empty.
- Track started, selected, succeeded, pending, failed, and unavailable checkout outcomes.

## Verification

- Feature tests for backend validation and local pending orders.
- Existing PayPal checkout tests stay green.
- `npm run build`.
- Browser smoke for `/store` empty bag, PayPal, Local, Card, and Apple Pay states.
