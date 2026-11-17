# New homepage workspace

`/newlanding` hosts the approved Takeover hero while the rest of the new homepage is developed. It uses a standalone Blade view and local assets, with no database changes or changes to `/`. The preview returns `noindex, nofollow` in its HTML and HTTP header, and does not load production analytics.

- View: `resources/views/newlanding.blade.php`
- Visuals, typography, countdown and calendar: `public/newlanding-assets/`
- Approved source: `reny-takeover-fondo-integrado.zip`, shared in the Reny homepage design thread on November 16, 2026.
- Desktop and mobile use their own continuous background artwork. Text and controls remain HTML.
- Event: October 2, 2027 at Rock & Folk. The live public event payload was checked on November 16, 2026: doors 7:30 PM, show 8:30 PM, America/Panama.
- The ticket button opens the existing `/store/checkout/reny-renteria-takeover-2027` checkout. This preview does not introduce a separate purchasing flow.
- Countdown and calendar use the published show time. If the event is rescheduled, update the view, `script.js` and `reny-takeover.ics` together.

Deploy through the existing main/Forge pipeline. There are no new environment variables or migrations. Fonts include their upstream license. Keep the page at this URL until Reny approves replacing the homepage.

## Validation

- Playwright: 320, 390, 768, 901 and 1440 px. Correct artwork, loaded fonts/images, no horizontal overflow or JavaScript errors. Desktop and mobile screenshots inspected.
- Preview and downloadable calendar return HTTP 200. Preview sends `X-Robots-Tag: noindex, nofollow`.
- Existing live ticket checkout opens successfully. No order was placed.
- Countdown checked one second before, at and after the show time; it never goes negative.
- Existing home, Takeover and analytics feature tests: 9 tests, 173 assertions.
- Vite production build, PHP/JS syntax, Pint and diff checks passed.
