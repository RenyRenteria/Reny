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

## Merch section (November 16, 2026)

The hero is followed by the approved collection banner and nine provisional collection pieces on ivory, with ink typography, ruby accents, DM Sans and 8 px controls. The grid uses three columns on desktop and two on phones, with the ninth piece centered. Each card opens a native dialog with a larger image; Escape, the close button or the backdrop closes it and returns focus. Without JavaScript the links open the image directly. “Ver toda la tienda” links to the existing `/store` landing page.

- Collection names and image mapping: `resources/views/partials/newlanding-merch.blade.php`.
- Generated concept images: `public/newlanding-assets/merch/`, in responsive 480/960 px WebP versions (about 524 KB total on disk). Cards load lazily.
- Generation prompts and provenance: `docs/newlanding-merch-prompts.md`.
- Products, names and imagery are explicitly marked provisional. No prices, stock claims, purchase buttons or catalog/database writes are introduced. Confirm the real nine products, images and checkout keys before wiring purchases.

Validation: Playwright at 320, 390, 700, 768, 901 and 1440 px; all nine images and fonts loaded, no horizontal overflow or page errors, all nine dialogs opened, close/Escape/backdrop and focus restoration passed, reduced motion disables image zoom. Desktop/mobile screenshots visually inspected. Existing home, Takeover and analytics tests passed (27 tests, 344 assertions). Blade compilation, JS syntax, Pint, Vite build and diff checks passed.

## Collection banner (November 17, 2026)

The approved “Lleva el Takeover contigo.” banner sits between the concert hero and the nine pieces. It reuses the image and layout from `reny-banner-coleccion-prototipo.zip`, approved in the homepage design thread. Desktop uses a wide composition with text to the left; mobile crops around the model and places the text over the dark lower portion. “Explorar la colección” scrolls to the existing `#coleccion` anchor. The collection heading below is compact to match the approved prototype.

- Banner template: `resources/views/partials/newlanding-merch-banner.blade.php`.
- Image: `public/newlanding-assets/merch/collection-banner.webp`, 1920 × 819, 45,090 bytes, lazy-loaded. Re-encoded from the supplied PNG without changing its composition.
- Banner text remains selectable HTML, with an H2 below the concert H1. The image is a generated fictional model; the visible provisional-imagery note remains above the products.
- `#coleccion-banner` links directly to the new banner. Smooth scrolling respects reduced-motion preferences. The stylesheet URL is versioned for deployment.

Validation: Playwright at 320, 390, 700, 768, 1000 and 1440 px; banner and all nine product images loaded, no horizontal overflow or JavaScript errors. Collection CTA, product dialog/Escape/focus return and reduced-motion scrolling passed. Mobile and desktop screenshots inspected. Existing home, Takeover and analytics tests: 39 passed, 482 assertions. Blade compilation, Pint, Vite production build and diff checks passed.

## Royal Pass section (November 17, 2026)

Royal Pass follows the nine pieces and the store link at `#royal-pass`. “Hay más de Reny para ti.” introduces exclusive content and the Royals community. The studio portrait blends into the ink background, with ivory typography, ruby accents and 8 px controls. On phones the portrait sits above the invitation. A compact signature footer includes a link back to the concert heading.

- Template: `resources/views/partials/newlanding-royal-pass.blade.php`.
- Photograph: Reny's approved recording-studio image, supplied in the homepage thread on November 17, 2026 as `529cd742_Screenshot 2026-11-17 at 8.46.39 AM.png`, re-encoded as `public/newlanding-assets/royal/reny-recording.webp` (399 × 621, 21,302 bytes). Its original warm colors are preserved, with CSS gradients blending it into the section. The top-aligned crop keeps Reny's head visible across desktop and mobile. The image loads lazily; the distinct filename and updated stylesheet version avoid stale cached assets.
- “Descubrir Royal Pass” opens the existing `store.checkout` route for `royal`. “Ya soy Royal” opens the existing `royals` route. No new purchase, authentication or entitlement flow.
- The video teaser is explicitly marked “Adelanto próximamente”. It is a static photograph, with no fake play button, video duration or playback request. Replace this cover with an approved public teaser when Reny supplies the video. Keep member-only video behind the existing entitlement checks.
- No price or additional membership promises are hardcoded. Current product terms remain in the existing checkout.

Validation: Playwright at 320, 390, 700, 768, 1000 and 1440 px; no horizontal overflow or JavaScript errors, photograph and fonts loaded, correct section order, keyboard focus and tab order, reduced-motion scrolling and return-to-top link passed. Existing merch dialog still opens and closes with Escape. Mobile and desktop screenshots inspected. Existing home, Takeover and analytics tests: 51 passed, 583 assertions. Blade compilation, Pint, Vite build and diff checks passed. The preview still responds with `noindex, nofollow`.

## Compact concert and Royal Pass (November 17, 2026)

The concert now sizes to its content instead of forcing a full viewport or an 880–1120 px mobile minimum. Smaller vertical gaps preserve the countdown and both actions while revealing the collection below. Royal Pass uses a shorter mobile portrait and tighter typography and spacing. The approved collection banner and nine-product grid retain their existing styles. The stylesheet URL is versioned for the update.

At 1440 × 900, the concert measures 675 px (previously 900) and Royal Pass 636 px (previously 838). At 390 × 844, they measure 683 px (previously 880) and 778 px (previously 1119). Sections can still grow naturally for smaller screens or enlarged text; content and controls are not clipped to a fixed height.

Validation: Playwright at widths 320, 375, 390, 700, 768, 900, 901, 1000, 1280, 1440 and 1920 px found no horizontal overflow or clipped copy. Desktop, mobile and tablet captures inspected. Countdown ticks, collection anchor, product dialog/Escape/focus return, reduced motion and return-to-top passed. Ticket, calendar, store and Royal Pass destinations are preserved; primary buttons remain at least 48 px tall. Existing home, Takeover and analytics tests passed (29 tests, 356 assertions).

## Half-height collection banner (November 17, 2026)

At Reny's request, the collection banner now measures 280 px on desktop (previously 560), 230 px on tablets (previously 460), and 300 px on phones (previously 600). The headline and collection button remain; the eyebrow, supporting sentence and decorative edition label have been removed. Smaller type and spacing, a top-aligned portrait and a darker mobile gradient keep the artwork and copy readable in the shorter space. Heights are minimums so enlarged text can expand the section naturally. The nine-product grid, concert and Royal Pass are unchanged. The stylesheet URL is versioned for deployment.

Validation: Playwright at widths 320, 375, 390, 700, 701, 768, 900, 1000, 1001, 1440 and 1920 px confirmed the target heights, loaded banner image and fonts, no horizontal overflow or clipped copy, nine product links and a 48 px collection button. Mobile and desktop screenshots inspected. Collection anchor, reduced-motion scrolling, product dialog, Escape and focus return passed with no page errors. Existing home, Takeover and analytics tests passed (27 tests, 344 assertions); Blade compilation, Pint and diff checks passed.
