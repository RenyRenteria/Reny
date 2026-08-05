# PR #213 Royal Pass desktop parity evidence

Captured from PR head `7730513` at a `1440 × 1200` viewport.

| Home reference | Royals implementation |
| --- | --- |
| ![Home Royal Pass reference](home-royal-pass-1440.png) | ![Royals Royal Pass without photos](royals-royal-pass-1440.png) |

## Computed parity

Both banners use the shared `<x-royal-pass-banner>` component and render with:

- width: `832px`
- height: `72px`
- padding: `12px 24px`
- border radius: `19.2px`
- the same red/gold radial and linear gradients

The only intentional content difference is the latest requirement: Home keeps its
three photos, while Royals renders `0` images and no `.home-royal-pass-images`
container.

## SPA metadata replay

Playwright navigated through the public SPA from Home to Royals. After navigation:

- URL: `http://127.0.0.1:8765/royals`
- canonical: `http://127.0.0.1:8765/royals`
- Open Graph URL: `http://127.0.0.1:8765/royals`
- Open Graph title: `Directo de Reny. Cerca de la comunidad. | Reny Renteria`
- Twitter title: `Directo de Reny. Cerca de la comunidad. | Reny Renteria`
- description: `Posts oficiales, anuncios y momentos exclusivos de Reny. La conversación continúa en el Live Chat.`

The replay verifies the runtime path covered by `tests/js/public-page-head.test.js`.
