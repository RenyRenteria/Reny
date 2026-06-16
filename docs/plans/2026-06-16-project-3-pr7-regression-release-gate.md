# Project 3 PR7 Regression And Release Gate Plan

Date: 2026-06-16

Source contract:

- GitHub issue: https://github.com/RenyRenteria/Reny/issues/36
- QA contract PR: https://github.com/RenyRenteria/Reny/pull/33

## Objective

Close Project 3 PR7 by adding an explicit regression gate around the admin CMS close criteria and a release checklist with a ship/no-ship verdict.

## Implementation Scope

- Add regression coverage for the critical close criteria that were not already explicit in the Project 3 feature tests.
- Keep existing implementation code unchanged unless a new regression test exposes a real gap.
- Document the release gate, automated commands, UAT coverage, final verdict, and blockers.

## Test Coverage Targets

- Admin RBAC:
  - Editors must not publish or schedule existing drafts by manipulating request payloads.
- Editorial workflow:
  - Audit logs must cover created, approval requested, updated, approved, published, scheduled, and archived actions.
- Release windows:
  - Member-first/open-later windows must block guests before the open date and allow guests after it.
  - Ended audience windows must remove access after expiry.
- Visibility gates:
  - Public routes and payloads must enforce gates server-side, not only hide UI.
- Upload failures:
  - Partial app-server upload failures must remove already stored files and leave no media records.
- Mux webhooks:
  - Direct-upload asset-created events must move pending assets into processing.
  - Stale signatures must be rejected.

## Validation Plan

- Run the focused Project 3 regression suite:

```bash
php artisan test tests/Feature/AdminAuthRbacTest.php tests/Feature/EditorialDomainWorkflowTest.php tests/Feature/AdminEditorialFormsPreviewSchedulingTest.php tests/Feature/AdminMediaLibraryTest.php tests/Feature/PublicCmsContentTest.php tests/Feature/Project3AdminCmsReleaseGateTest.php
```

- Run the full Laravel test suite:

```bash
php artisan test
```

- Run browser UAT or HTTP-equivalent smoke coverage for:
  - Admin login/dashboard access.
  - Admin/editorial workspace.
  - Public music, videos, photos, store, and community pages.

## Release Decision Rule

- Code gate can pass only when focused and full automated suites are green.
- Product release remains no-ship while the Mux secret rotation blocker from the source contract is unresolved.
