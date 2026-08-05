# Issue 149: auth, password reset, and throttling hardening

## Goal

Close the public auth, admin auth, password recovery, and checkout abuse gaps without changing the successful customer flows.

## Implementation

1. Centralize security rate-limit key construction so route middleware and controllers use the same normalized identifier/email plus IP scope.
2. Register named limiters for public login, admin login, password-reset requests, and checkout mutations while retaining the existing community limiters.
3. Apply the named middleware to the public/admin login routes, forgot-password submission, and every public PayPal/local checkout mutation.
4. Clear the matching login limiter after successful public or admin authentication. Log failed admin attempts at warning level with a reason and hashed email rather than credentials or raw identifiers.
5. Complete password recovery end-to-end: resolve either email or normalized phone to the account email, send the broker reset notification with a non-enumerating response, expose the reset form, and update the password through Laravel's broker.
6. Document production-safe environment defaults in `.env.example` while preserving clear local-development overrides.

## Verification

- Feature tests prove public login returns 429 after five attempts and a valid login clears previous failures.
- Feature tests prove admin login returns 429 after three attempts, clears after success, and logs invalid/non-admin failures without raw email.
- Feature tests prove PayPal/local guest mutations share one 20-request IP limiter, including cancel requests that do not carry an identifier, and return 429 after the threshold.
- Feature tests prove recovery works from email and phone, unknown accounts receive the same public response, reset tokens update the password, and reset-email requests are throttled.
- Run focused auth/security tests, the full PHP suite, formatting, and browser smoke checks for the forgot/reset forms when a local runtime is available.

## Risks and mitigations

- Named throttle middleware hashes cache keys internally. A shared helper computes the exact named key when clearing after success, with regression coverage around reset behavior.
- Password recovery can leak account existence. Controller responses remain identical for known and unknown identifiers.
- Checkout clients make multiple calls per purchase. A shared 20/minute guest-IP budget leaves room for create, cancel, and capture retries while ensuring absent or manipulated identifiers cannot bypass it; authenticated users retain a separate user/IP budget.
- Admin logs can expose personal data. Only a one-way email hash, IP, and failure reason are recorded; passwords and raw identifiers are never logged.
