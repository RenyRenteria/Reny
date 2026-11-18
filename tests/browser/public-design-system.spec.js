import { test, expect } from '@playwright/test';

const paths = ['/', '/store', '/shows', '/royals', '/music', '/music/albums', '/music/singles', '/music/playlists', '/videos', '/photos', '/merch', '/bio', '/contacto', '/login', '/register', '/forgot-password', '/store/checkout/royal'];

test.beforeEach(async ({ page }) => {
    // Test this application's UI without depending on third-party video embeds.
    await page.route(/https:\/\/(www\.)?youtube\.com\/embed\//, (route) => route.abort());
});

for (const path of paths) {
    test(`public layout ${path}`, async ({ page }, testInfo) => {
        const errors = [];
        page.on('pageerror', (error) => errors.push(error.message));
        const response = await page.goto(path);
        expect(response.status()).toBe(200);
        await page.evaluate(() => document.fonts.ready);
        await expect(page.locator('.site-header')).toHaveCount(1);
        await expect(page.locator('.site-footer')).toHaveCount(1);
        await expect(page.locator('body')).toHaveCSS('background-color', 'rgb(245, 242, 236)');
        const report = await page.evaluate(() => ({
            width: innerWidth,
            scrollWidth: document.documentElement.scrollWidth,
            overflow: [...document.querySelectorAll('main *')].filter((el) => el.getBoundingClientRect().width > innerWidth && getComputedStyle(el).position !== 'absolute').map((el) => ({ tag: el.tagName, class: el.className, width: el.getBoundingClientRect().width })),
            headings: [...document.querySelectorAll('h1,h2')].filter((el) => el.getBoundingClientRect().height).map((el) => ({ text: el.textContent.trim(), color: getComputedStyle(el).color, background: getComputedStyle(el).backgroundImage })),
        }));
        await testInfo.attach('layout.json', { body: JSON.stringify(report, null, 2), contentType: 'application/json' });
        expect.soft(report.scrollWidth).toBeLessThanOrEqual(report.width + 1);
        expect.soft(errors).toEqual([]);
        if ([390, 1440].includes(page.viewportSize().width)) {
            await page.screenshot({ path: testInfo.outputPath('page.png'), fullPage: true });
        }
        if (page.viewportSize().width <= 900) {
            await expect(page.locator('.site-desktop-nav')).toBeHidden();
            const menu = page.locator('.site-mobile-nav');
            await menu.locator('summary').click();
            await expect(menu.getByRole('link')).toHaveCount(5);
            await menu.getByRole('link').first().focus();
            await page.keyboard.press('Escape');
            await expect(menu).not.toHaveAttribute('open');
            await expect(menu.locator('summary')).toBeFocused();
        } else {
            await expect(page.locator('.site-mobile-nav')).toBeHidden();
            await expect(page.locator('.site-desktop-nav a')).toHaveCount(5);
        }
    });
}

test('navigation updates active state and preserves the music player node', async ({ page }) => {
    await page.goto('/music');
    await page.evaluate(() => { document.querySelector('[data-global-music-player]').dataset.browserCheck = 'persistent'; });
    const nav = page.locator(page.viewportSize().width <= 900 ? '.site-mobile-nav' : '.site-desktop-nav');
    for (const [label, path] of [['Shop', '/store'], ['Shows', '/shows'], ['Royal Pass', '/royals'], ['Music', '/music']]) {
        if (page.viewportSize().width <= 900) await nav.locator('summary').click();
        await nav.getByRole('link', { name: label, exact: page.viewportSize().width > 900 }).click();
        await expect(page).toHaveURL(new RegExp(`${path}$`));
        await expect(page.locator('[data-global-music-player]')).toHaveAttribute('data-browser-check', 'persistent');
        await expect(nav.getByRole('link', { name: label, exact: page.viewportSize().width > 900 })).toHaveAttribute('aria-current', 'page');
    }
    await page.goBack();
    await expect(page).toHaveURL(/\/royals$/);
    await expect(page.locator('[data-global-music-player]')).toHaveAttribute('data-browser-check', 'persistent');
});

test('member account and access states remain usable', async ({ page }, testInfo) => {
    await page.goto('/login');
    await page.getByLabel('Email or phone', { exact: true }).fill('qa+royal-active@renyrenteria.test');
    await page.getByLabel('Password', { exact: true }).fill(process.env.RENY_QA_PASSWORD);
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    await expect(page).toHaveURL(/\/account$/);
    await expect(page.getByRole('heading', { name: 'Profile', exact: true })).toBeVisible();
    await expect(page.locator('.site-member')).toContainText('ROYAL MEMBER');
    expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(page.viewportSize().width + 1);
    await page.screenshot({ path: testInfo.outputPath('account.png'), fullPage: true });
    await page.goto('/royals');
    await expect(page.locator('[data-community-live-chat-form]')).toBeVisible();
    await expect(page.locator('[data-royal-pass-banner]')).toHaveCount(0);
});

test('footer disclosure and keyboard skip link work', async ({ page }) => {
    await page.goto('/store');
    await page.keyboard.press('Tab');
    await expect(page.locator('.site-skip')).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(page.locator('#site-content')).toBeFocused();
    const faq = page.locator('.site-footer details').filter({ has: page.locator('#footer-faq-label') });
    await faq.locator('#footer-faq-label').click();
    await expect(faq.locator('.footer-panel')).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(faq).not.toHaveAttribute('open');
    await expect(faq.locator('#footer-faq-label')).toBeFocused();
});
