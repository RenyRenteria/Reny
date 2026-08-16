# Golden Stage dedicated checkout UAT

Browser flow and responsive coverage were validated on August 10, 2026. The
implementation was rebased onto the current `main` and its PHP, JS, build, Pint,
and diff checks were rerun on August 16, 2026.

## Coverage

- Dedicated checkout renders inline without `bagLayer` or automatic modal opening.
- CMS product title, summary, image, price, currency, details, and benefits remain visible.
- PayPal initializes inline and keeps the existing create/cancel/capture endpoints.
- Empty submission renders four inline field errors and focuses the first invalid field.
- Successful mocked PayPal approval replaces the payment column with inline confirmation.
- Desktop sidebar and mobile bottom navigation keep the approved Golden Stage behavior.
- Golden background and all three spotlights stay static during checkout.
- No horizontal overflow at 320, 375, 430, 768, 1024, or 1440 pixels.
- At 375 x 500 pixels, the payment status and panel clear the fixed bottom navigation.

## Homepage reference

The checkout deliberately reuses the homepage shell, warm gold stage, dark
panels, typography, rounded controls, sidebar, and fixed mobile navigation.

- [Homepage desktop reference, 1440px](../pr-207/home-desktop-1440px.jpg)
- [Homepage mobile reference, 375px](../pr-207/home-375px.jpg)

## Checkout screenshots

- [Checkout desktop, 1440px](desktop.png)
- [Checkout mobile, 375px](mobile.png)
