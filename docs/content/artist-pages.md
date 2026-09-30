# Artist pages — September 2026

The public navigation is Home, Videos, Música, Merch, Shows, Bio, Contacto, in that order on desktop and mobile. The existing `/store`, `/photos`, and `/royals` routes remain available. Merch uses only merchandise from the published Store CMS catalog and its existing checkout.

## Biography and image sources

The biography comes from Reny's supplied two-page “Final - Reny Renteria - Project Intelligence (2).pdf”, received September 29, 2026. It retains the supplied education, television, film, theatre, festival, skills, musical philosophy and discography. The September 21, 2026 concert is described as a past performance.

Bio and Contact use the four recent concert photographs supplied directly by Reny in this thread on September 29, 2026. These replace the initial selection from the site's older public archive, including the pages' social preview images. No third-party press photos or generated likenesses were added.

- `/images/artist/reny-live-portrait.webp`: Bio hero and social preview; source `WhatsApp Image 2026-09-21 at 11.36.02 PM.jpeg` (960 × 1280).
- `/images/artist/reny-live-dancers.webp`: Bio gallery, singing with dancers; source `DSC04439.JPG` (exported at 1400 × 2100).
- `/images/artist/reny-live-stage.webp`: Bio gallery, wide stage view; source `DSC04456 (1).JPG` (exported at 1400 × 2100).
- `/images/artist/reny-live-red.webp`: Contact hero and social preview; source `WhatsApp Image 2026-09-22 at 1.30.45 AM.jpeg` (exported at 1200 × 1875).

The supplied photos are encoded as WebP for the website, preserving their composition and colors. CSS positions the portraits toward the top and the gallery images toward the bottom so the performers stay visible on desktop and mobile. Captions describe the images without assuming an event date or venue. Original archive images remain available to other existing pages.

## Contact

The contact page includes a “contactar por correo” button linking to `mailto:reny@portierstrategy.com`, supplied and requested by Reny on September 29, 2026. It opens the visitor's configured email application. The official Instagram profile, `https://www.instagram.com/renyrenteria/`, remains available as a secondary contact option. There is no contact form or server-side email delivery.

## Validation

- 421 PHP tests / 4,793 assertions; 37 JavaScript tests; Pint and Vite build passed.
- Playwright: Bio, Contacto and Merch at 320, 375, 430, 768, 1024 and 1440px; no horizontal overflow, correct active item, 44px minimum navigation targets, final content clear of the mobile menu.
- Home, Videos, Music and Shows checked at 320 and 1440px. Short desktop navigation also checked at 1024 × 500.
- All three biography photos and Contact/Merch images loaded. New pages preserve the shared player element across navigation; page language, title, canonical URL and browser Back update correctly.
- Merch opens the existing checkout with the matching product and amount. Live PayPal payment was not attempted: local credentials are not configured.

### Photo refresh

- ArtistPagesTest and PublicNavigationTest: 4 PHP tests / 483 assertions passed; Pint and Vite build passed.
- Playwright rechecked Bio and Contact at 320, 375, 430, 768, 1024 and 1440px: all four supplied photos load, no horizontal overflow or JavaScript page errors, updated Open Graph/Twitter images, and the Instagram destination is preserved.
- Desktop and mobile screenshots were inspected for portrait and stage framing. The four WebP assets total about 382 KiB, compared with about 12 MiB for the supplied originals.
