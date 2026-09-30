# Artist pages — September 2026

The public navigation is Home, Videos, Música, Merch, Shows, Bio, Contacto, in that order on desktop and mobile. The existing `/store`, `/photos`, and `/royals` routes remain available. Merch uses only merchandise from the published Store CMS catalog and its existing checkout.

## Biography and image sources

The biography comes from Reny's supplied two-page “Final - Reny Renteria - Project Intelligence (2).pdf”, received September 29, 2026. It retains the supplied education, television, film, theatre, festival, skills, musical philosophy and discography. The September 21, 2026 concert is described as a past performance.

All selected photos already belong to the site's public image archive. No third-party press photos or generated likenesses were added. The image search also found Telemetro, El Siglo and Día a Día coverage, but those images are not needed for this implementation.

- `/images/photos/cover.jpg`: biography portrait at a vocal microphone.
- `/images/photos/studio.jpg`: recording session, illustrating musical creation.
- `/images/photos/performance.jpg`: television performance, illustrating stage work and the Contact page.

Captions do not attribute the photos to specific dated events that cannot be established from the archive.

## Contact

The initial contact destination is the official Instagram profile, `https://www.instagram.com/renyrenteria/`, consistent with the project's existing Instagram reference. Booking email/WhatsApp was requested from Reny and is not assumed from admin accounts or private contact details. The page links directly to Instagram; there is no unsent form or implied email delivery.

## Validation

- 421 PHP tests / 4,793 assertions; 37 JavaScript tests; Pint and Vite build passed.
- Playwright: Bio, Contacto and Merch at 320, 375, 430, 768, 1024 and 1440px; no horizontal overflow, correct active item, 44px minimum navigation targets, final content clear of the mobile menu.
- Home, Videos, Music and Shows checked at 320 and 1440px. Short desktop navigation also checked at 1024 × 500.
- All three biography photos and Contact/Merch images loaded. New pages preserve the shared player element across navigation; page language, title, canonical URL and browser Back update correctly.
- Merch opens the existing checkout with the matching product and amount. Live PayPal payment was not attempted: local credentials are not configured.
