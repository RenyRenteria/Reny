import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './tests/e2e',
    testMatch: 'paypal-sandbox.spec.js',
    fullyParallel: false,
    forbidOnly: true,
    retries: 0,
    workers: 1,
    timeout: 180_000,
    expect: {
        timeout: 30_000,
    },
    reporter: [['line']],
    use: {
        baseURL: process.env.PAYPAL_E2E_BASE_URL,
        browserName: 'chromium',
        headless: true,
        screenshot: 'off',
        trace: 'off',
        video: 'off',
    },
});
