# Catalog And Fallback Config

## Goal

Move storefront catalog defaults, community fallback content, and store RSVP event defaults out of business logic and into versioned Laravel config while preserving the current public payloads.

## Scope

- Add a `reny_catalog` config namespace for product catalog defaults, community fallback posts/poll/clubs, and RSVP event defaults.
- Update services/controllers to resolve configured defaults through `config()` only after CMS/DB sources have had first priority.
- Add tests proving config-level changes affect catalog, community, and RSVP resolution without editing service/controller logic, while CMS/DB records still win when present.

## QA Notes

- Public store/community/RSVP behavior should remain unchanged with the shipped config values.
- CMS-backed product/event records and persisted country club records take precedence over versioned config when present.
- Any production change to this config requires normal Laravel config cache refresh during deploy.
