# Public website design system rollout

This change applies the ivory / ink / ruby system approved in `/newlanding` to the real public website. The reference guide is PR #289. The standalone preview remains available; its provisional merchandise is not published as purchasable inventory.

## Coverage

- Shared signature header and mobile disclosure: Shop, Shows, Royal Pass, Music, My Account. Royal Pass opens the existing Royals page; members and guests retain their existing access rules. Music, Videos and Photos also share a content navigation row. Biography, Contact, Merch, Photos and Videos remain reachable in the footer.
- Home: the next CMS event leads, then the current release/video and album, with Royal Pass at the end. The Takeover event uses its approved integrated photography. Event dates, countdown and purchasing actions still come from the CMS. If there is no future event, the release leads. The live catalog is retained instead of replacing it with nine provisional concept products.
- Shop, Shows, Music, nested music collections and albums, Videos, Photos, Royals, clubs, biography and contact use the common layout and tokens.
- Account, points, sign-in, registration, password recovery/reset, access errors, checkout, merchandise checkout/tracking and public content share the theme.
- Local DM Sans 400/500/600, 8px fields/buttons/dialogs, straight open cards, 48px primary controls, visible focus and reduced motion.
- Desktop/sidebar and mobile bottom navigation are replaced by one header. The member card moves to the footer, preserving real name, avatar, access state and sign-in/account links.
- Public-page replacements keep the shared header/footer inside the replacement root; delegated disclosure handlers continue to work after navigation. The persistent audio player keeps its existing identity.

## Implementation

`resources/css/features/site-design-system.css` defines the scoped `.reny-site` theme. Existing feature CSS is in the `legacy` cascade layer, after Tailwind's layers, preserving its previous precedence. New public styles take precedence without specificity escalation. The admin does not opt into `.reny-site` and retains its templates/styles. `/newlanding` still uses its own standalone assets.

No migrations, product changes, payment provider changes or entitlement changes. The CI seed step runs only against its local SQLite database; the existing QA seeder refuses production use by default.

## Verification

Run `composer test`, `npm run test:js`, `npm run build`, and `vendor/bin/pint --dirty --test`.

Browser checks run with `npm run test:browser`, using an isolated local Laravel server and the existing QA accounts. Set `RENY_QA_PASSWORD` before seeding with `php artisan db:seed --class=QaAccountStateSeeder`. For the merch checkout, seed the isolated SQLite database with `php artisan db:seed --class=QaMerchProductSeeder` and set `YOYCOL_ENABLED=true YOYCOL_SALES_ENABLED=true` on the local browser server. The browser supplies the shipping quote and a test PayPal SDK. Chromium is installed with `npx playwright install --with-deps chromium`.

The 80 browser cases cover 17 public routes at 320, 390, 768 and 1440px, mobile disclosure/Escape/focus, active navigation, back navigation, preservation of the audio-player node, account sign-in, Royal access, footer disclosure and skip link. Desktop/mobile screenshots and traces are uploaded as `public-website-browser-evidence` in CI. These checks do not place an order or charge a payment method.

Local Playwright MCP was blocked by an occupied browser profile; the local Chromium launcher was also unavailable under the runtime's permissions. Browser validation therefore runs through the repository's CI rather than claiming an unexecuted local UAT.

## Results, November 17, 2026

- Laravel: 447 tests, 5,038 assertions passed.
- JavaScript: 38 tests passed. Vite production build, Blade compilation, Pint and diff checks passed.
- Chromium in CI: all 80 browser cases passed, including all 17 routes at four widths, authenticated Royal access, keyboard disclosures and persistent player navigation. No horizontal overflow or page JavaScript errors in the route matrix.
- Evidence: https://github.com/RenyRenteria/Reny/actions/runs/44236096204 (implementation `780255f`). Desktop/mobile captures were reviewed to remove inherited gold panels, improve dark-surface secondary actions and reduce oversized account event imagery.
- QA uses local fixture content, so it does not certify the production CMS dataset or live payment processing. PayPal is intentionally unconfigured in CI; no payment was placed. YouTube embeds are blocked in browser checks to keep third-party availability from affecting layout verification.
- GitHub refused a review request to `quezadaesteban` because that account is not a collaborator in this repository. The PR remains available for repository reviewers.
