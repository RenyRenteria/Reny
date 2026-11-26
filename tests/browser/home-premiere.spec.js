import { test, expect } from '@playwright/test';

test('home autoplays muted video first, then tickets, album and Royal Pass', async ({ page }) => {
    const embeds = [];
    await page.route('https://www.youtube.com/embed/**', async (route) => {
        embeds.push(route.request().url());
        await route.fulfill({ contentType: 'text/html', body: '<html><body>Video player</body></html>' });
    });
    await page.goto('/');
    await page.evaluate(() => document.fonts.ready);
    await expect(page.locator('.home-stack > section').first()).toHaveClass(/home-premiere/);
    await expect(page.locator('main h1')).toHaveCount(1);
    const sections = await page.locator('.home-stack > section').evaluateAll((nodes) => nodes.map((node) => ({
        className: node.className,
        y: node.getBoundingClientRect().y,
    })));
    expect(sections.map((section) => section.className)).toEqual([
        'home-video-hero home-premiere', 'home-shows', 'home-music', 'site-royal-close',
    ]);
    expect(sections.map((section) => section.y)).toEqual(sections.map((section) => section.y).sort((a, b) => a - b));
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(page.viewportSize().width);
    const player = page.locator('[data-home-video-frame] iframe');
    await expect(player).toBeVisible();
    const bounds = await player.boundingBox();
    expect(bounds.y + bounds.height / 2).toBeLessThan(page.viewportSize().height);
    await expect.poll(() => embeds.length).toBe(1);
    await expect(player).toHaveCount(1);
    await expect(player).toHaveAttribute('src', /youtube\.com\/embed\/UWDLtZCoTag\?autoplay=1&mute=1&playsinline=1&rel=0/);
    await expect(player).toHaveAttribute('allow', /autoplay/);
    await expect(player).not.toBeFocused();
    await expect(page.getByRole('link', { name: 'Open YouTube' })).toBeVisible();
    const album = await page.locator('.home-album-image').boundingBox();
    expect(Math.abs(album.width - album.height)).toBeLessThan(1);
    for (const control of ['.home-show-actions .home-pill-button', '.home-play-here', '.home-streaming-link', '.home-unlock-button']) {
        for (const bounds of await page.locator(control).evaluateAll((nodes) => nodes.map((node) => ({ width: node.getBoundingClientRect().width, height: node.getBoundingClientRect().height })))) {
            expect(bounds.height).toBeGreaterThanOrEqual(48);
            expect(bounds.width).toBeGreaterThanOrEqual(48);
        }
    }
});

test('home ticket and Royal Pass buttons still open checkout', async ({ page }) => {
    await page.route('https://www.youtube.com/embed/**', (route) => route.fulfill({ contentType: 'text/html', body: 'Video player' }));
    await page.goto('/');
    for (const selector of ['.home-show-actions [data-buy]', '.site-royal-close [data-buy]']) {
        const trigger = page.locator(selector);
        await trigger.click();
        await expect(page.locator('#bagLayer')).toBeVisible();
        await expect(page.locator('#bagLayer')).toContainText(await trigger.getAttribute('data-buy-name'));
        await page.keyboard.press('Escape');
        await expect(page.locator('#bagLayer')).toBeHidden();
    }
});

test('video autoplays on each return home through public navigation', async ({ page }) => {
    await page.route('https://www.youtube.com/embed/**', (route) => route.fulfill({ contentType: 'text/html', body: 'Video player' }));
    await page.goto('/music');
    await page.evaluate(() => { window.homeNavigationCheck = true; });
    for (let visit = 0; visit < 2; visit++) {
        await page.locator('.site-header .site-signature').click();
        await expect(page).toHaveURL(/\/$/);
        const player = page.locator('[data-home-video-frame] iframe');
        await expect(player).toHaveCount(1);
        await expect(player).toBeVisible();
        await expect(player).toHaveAttribute('src', /autoplay=1&mute=1&playsinline=1/);
        expect(await page.evaluate(() => window.homeNavigationCheck)).toBe(true);
        await page.goBack();
        await expect(page).toHaveURL(/\/music$/);
        await expect(page.locator('[data-home-video-frame] iframe')).toHaveCount(0);
    }
});
