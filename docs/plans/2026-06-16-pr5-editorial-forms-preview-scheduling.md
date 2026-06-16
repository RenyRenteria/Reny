# PR5 Editorial Forms, Preview and Scheduling Plan

Date: 2026-06-16

Issue: https://github.com/RenyRenteria/Reny/issues/39

## Scope

- Add admin content forms for every V1 `ContentType`.
- Reuse existing `EditorialContent`, release windows, audit log, and media library models.
- Keep editors limited to draft/prep flows while admins and artist admins can publish or schedule.
- Add private admin-only preview with `noindex` headers and meta tags.
- Normalize scheduled dates and release windows from `America/Panama`.

## Implementation Notes

- `App\Support\EditorialContentForms` is the source of truth for type labels, fields, and per-type validation.
- The admin content screen supports create and edit flows with the same form.
- Media selection writes to `content_media_assets` through the workflow service.
- Scheduling can create new scheduled content or schedule an existing draft/content item.
- Autosave is not included in this slice; defensive save is covered by explicit draft/update actions and server-side validation.

## Validation

- Feature tests cover editor draft creation for every V1 type.
- Feature tests cover admin publishing for every V1 type.
- Feature tests cover private/noindex preview.
- Feature tests cover Panama-time scheduling.
- Feature tests cover representative per-type validation failures.
