# Issue 55 Account, Permissions, and User States Plan

## Goal

Complete the QA-enabling account and access-state work for Project 4 so testers can validate guest, registered, Royal active, Royal expired, refunded, and payment-failed flows without manual account setup.

## Implementation Scope

- Extend account state semantics without changing the existing stored `royal_status=open` registered-user baseline.
- Add deterministic QA fixture accounts for registered, Royal active, Royal expired, payment failed, and refunded states.
- Make `/account` display the current user state clearly on desktop and mobile.
- Replace raw Royal access aborts with a branded permission denied/reactivation surface that does not render protected payloads.
- Preserve post-login redirect behavior for protected routes.
- Add auth/account/denied analytics metadata and test coverage.

## Verification

- Focused Laravel feature tests for auth, account dashboard, entitlement gates, public CMS gates, analytics, checkout refund state, and QA fixtures.
- Browser smoke check for `/login`, `/account`, and denied Royal route if the local app can start.
