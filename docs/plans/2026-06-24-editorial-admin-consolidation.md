# Editorial Admin Consolidation

Issue: https://github.com/RenyRenteria/Reny/issues/153
Branch: `dos/issue-153-editorial-admin-consolidation`

## Scope

- Extract shared music editorial validation, metadata normalization, release windows, upload handling, and media asset syncing into one service.
- Keep `EditorialContentController` responsible for the content form endpoint and non-music content rules.
- Keep `EditorialActionController` responsible for action endpoints and delegate non-music rules to `EditorialContentForms`.
- Make parked admin routes explicit so read surfaces do not look accidentally active.
- Replace the obscure default admin path with the non-secret `admin` default.

## Verification

- Run focused editorial/admin path tests.
- Run formatter.
- Ask Cuatro for QA notes before final handoff.
