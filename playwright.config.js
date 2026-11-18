import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './tests/browser',
    timeout: 45_000,
    fullyParallel: true,
    workers: 2,
    reporter: [['list'], ['html', { open: 'never' }]],
    use: {
        baseURL: 'http://127.0.0.1:8000',
        screenshot: 'only-on-failure',
        trace: 'retain-on-failure',
    },
    projects: [320, 390, 768, 1440].map((width) => ({
        name: `${width}px`,
        use: { browserName: 'chromium', viewport: { width, height: 900 } },
    })),
    webServer: {
        command: 'php artisan serve --host=127.0.0.1 --port=8000',
        url: 'http://127.0.0.1:8000',
        reuseExistingServer: !process.env.CI,
    },
});
