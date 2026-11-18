import { expect, test } from '@playwright/test';
import { fillMerchDelivery } from '../support/merch-delivery.js';

test('public merch opens size, delivery and quoted total', async ({ page }, testInfo) => {
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.addInitScript(() => {
        window.paypal = { Buttons: () => ({ render: async () => {} }) };
    });
    await page.route('**/checkout/yoycol/*/quote', async (route) => {
        expect(route.request().postDataJSON()).toMatchObject({
            variant_id: 'large', quantity: 1, address: { country: 'US', city: 'San Jose' },
        });
        await route.fulfill({ json: {
            quote_id: 'browser-quote', currency: 'USD', subtotal_cents: 4800, shipping_cents: 700,
            amount_cents: 5500, shipping_level: 'standard', expires_at: new Date(Date.now() + 60000).toISOString(),
            levels: [{ code: 'standard', name: 'Standard', description: '5–7 days', amount_cents: 700 }],
        } });
    });

    await page.goto('/merch');
    const card = page.locator('article').filter({ hasText: 'QA Merch Hoodie' });
    await card.getByRole('link', { name: 'Choose size' }).click();
    const checkout = page.locator('[data-yoycol-checkout]');
    await expect(checkout.getByRole('heading', { name: 'QA Merch Hoodie' })).toBeVisible();
    await fillMerchDelivery(checkout, 'qa@example.test');
    await checkout.locator('[name="variant_id"]').selectOption('large');
    const calculate = checkout.getByRole('button', { name: 'Calculate shipping' });
    await expect(calculate).toHaveCSS('color', 'rgb(245, 242, 236)');
    await calculate.click();

    await expect(checkout.locator('[data-shipping]')).toHaveText('$7.00');
    await expect(checkout.locator('[data-total]')).toHaveText('$55.00 USD');
    await expect(checkout.getByRole('status')).toContainText('Total confirmed');
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(page.viewportSize().width + 1);
    expect(errors).toEqual([]);
    await testInfo.attach('merch-quote', { body: await page.screenshot({ fullPage: true }), contentType: 'image/png' });
});
