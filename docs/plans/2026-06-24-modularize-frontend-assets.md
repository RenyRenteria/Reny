# Modularize Frontend Assets

Issue: https://github.com/RenyRenteria/Reny/issues/152

## Goal

Split the frontend monoliths into feature-oriented modules without intentional visual or behavioral changes.

## Scope

- Keep `resources/js/app.js` as the Vite entrypoint.
- Move shared analytics and binding helpers into a reusable JS module.
- Move music player, community, checkout/store, admin, and adjacent public UI behavior into feature modules.
- Keep `resources/css/app.css` as the Vite entrypoint and preserve cascade order through CSS imports.
- Split CSS into ordered domain files so generated output stays equivalent.

## Verification

- Run `npm run build`.
- Run browser smoke checks for home, music/player, community, checkout/store, and admin.
- Capture any smoke blockers separately from the structural refactor.
