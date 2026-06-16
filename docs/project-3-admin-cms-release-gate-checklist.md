# Project 3 Admin CMS Release Gate Checklist

Date: 2026-06-16

Source contract: https://github.com/RenyRenteria/Reny/pull/33

## Verdict

No-ship for QA/prod until the Mux secrets called out in the source contract are rotated and loaded only through secrets/env vars.

Code can be considered release-gate ready after:

- The focused Project 3 regression suite passes.
- The full Laravel test suite passes.
- Browser UAT or HTTP-equivalent smoke checks pass for critical admin and public flows.
- No P0/P1 blockers remain open.

## Automated Gate

Run:

```bash
php artisan test tests/Feature/AdminAuthRbacTest.php tests/Feature/EditorialDomainWorkflowTest.php tests/Feature/AdminEditorialFormsPreviewSchedulingTest.php tests/Feature/AdminMediaLibraryTest.php tests/Feature/PublicCmsContentTest.php tests/Feature/Project3AdminCmsReleaseGateTest.php
```

Coverage map:

- Admin RBAC:
  - `tests/Feature/AdminAuthRbacTest.php`
  - `tests/Feature/Project3AdminCmsReleaseGateTest.php`
- Editorial workflow and audit:
  - `tests/Feature/EditorialDomainWorkflowTest.php`
  - `tests/Feature/AdminEditorialFormsPreviewSchedulingTest.php`
  - `tests/Feature/Project3AdminCmsReleaseGateTest.php`
- Release windows:
  - `tests/Feature/EditorialDomainWorkflowTest.php`
  - `tests/Feature/AdminEditorialFormsPreviewSchedulingTest.php`
  - `tests/Feature/Project3AdminCmsReleaseGateTest.php`
- Visibility gates:
  - `tests/Feature/PublicCmsContentTest.php`
  - `tests/Feature/Project3AdminCmsReleaseGateTest.php`
- Upload failures:
  - `tests/Feature/AdminMediaLibraryTest.php`
- Mux direct upload and webhooks:
  - `tests/Feature/AdminMediaLibraryTest.php`
  - `tests/Feature/Project3AdminCmsReleaseGateTest.php`

## Critical UAT Checklist

- Admin login redirects anonymous users to `/admin/login`.
- Admin dashboard loads for an admin or artist admin.
- Editor can create and prepare content without publishing directly.
- Admin or artist admin can publish and schedule content.
- Preview is private, noindex, and unavailable without admin session.
- Guest public pages load for `/`, `/videos`, `/photos`, `/store`, and `/community`.
- Member/royal/purchased content is enforced by backend route and payload checks.
- Member-first/open-later release window blocks guests before the open date and opens later.
- App-server upload failure returns a clear error and leaves no corrupt media records.
- Mux webhook accepts valid events, rejects invalid/stale signatures, and updates processing state.

## Release Blockers

- Mux secrets must be rotated before QA/prod.

## Validation Evidence

Local validation on 2026-06-16:

- Focused Project 3 regression suite passed:
  - 50 tests
  - 496 assertions
- Full Laravel suite passed:
  - 104 tests
  - 894 assertions
- Pint passed:
  - `./vendor/bin/pint --dirty`
- Asset build passed:
  - `npm run build`
- Browser UAT passed on local SQLite smoke data:
  - Desktop public pages: `/`, `/videos`, `/photos`, `/store`, `/community`
  - Desktop admin: anonymous `/admin` redirect, admin login, dashboard, content workspace, post create form
  - Mobile public: `/community`
  - Mobile admin: `/admin/content`
  - Browser console: 0 warnings, 0 errors

## Release Notes For Handoff

- PR7 is a regression and release gate PR. It should not introduce new CMS product behavior unless the tests expose a defect that must be fixed to pass the gate.
- The old admin-panel PR mentioned in the source contract is not treated as Project 3 completion because it covers only a subset of the current CMS contract.
- Final reviewer: `reviewer:cuatro`.
