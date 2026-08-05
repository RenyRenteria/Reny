# Fix Mobile Home Cards and Royal Pass Gradient

## Goal

Repair the mobile Home card regression introduced by the golden stage-light reskin without changing CMS data, navigation, playback, or the desktop content layout.

## Implementation

1. Use the current Album card height as a shared mobile card-size token.
2. Apply that minimum height and the Album artwork column width to the visible Concert card so both cards render at the same dimensions at supported phone widths.
3. Raise the `Play Here` control to a 44px minimum touch target and increase its horizontal footprint while retaining the current playback trigger markup.
4. Replace only the Royal Pass surface background with a deep red gradient, retaining the gold border, cream copy, and gold CTA that connect it to the Home visual language.
5. Add a focused CSS contract test to prevent the shared card height, touch target, and red gradient from regressing.

## Verification

- Run the Home feature tests, PHP formatter check, JavaScript tests, and production build.
- Verify the Home page in a browser at 320px, 375px, 390px, and 430px widths.
- Confirm Concert and Album card width/height parity, no page overflow, undistorted media, and a 44x44px-or-larger `Play Here` target.
- Verify a desktop viewport to confirm the card layout is unchanged and the Royal Pass red gradient remains readable.
- Capture representative mobile and desktop screenshots for the pull request.

## Risks

- A fixed card height could clip longer CMS content, so the shared value is applied as a minimum height rather than an overflow-hiding fixed height.
- The mobile card artwork column narrows below 380px in the existing design; Concert must mirror the same breakpoint to maintain parity without crowding copy.
