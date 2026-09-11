// @ts-check
const { defineConfig, devices } = require('@playwright/test');
const { ADMIN_STORAGE_STATE } = require('./specs/support/paths');

// The test application is served by `symfony serve`, which uses a locally issued certificate, so HTTPS
// errors are ignored rather than requiring the local CA to be trusted on every machine and in CI.
const baseURL = process.env.PLAYWRIGHT_BASE_URL ?? 'https://127.0.0.1:8080';

module.exports = defineConfig({
    testDir: './specs',
    // The suite drives one shared application, and Sylius' admin is stateful (the cart in particular), so
    // running files in parallel against it produces flakiness rather than speed
    workers: 1,
    fullyParallel: false,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    reporter: process.env.CI ? [['github'], ['html', { open: 'never' }]] : [['list']],

    use: {
        baseURL,
        ignoreHTTPSErrors: true,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },

    projects: [
        {
            name: 'setup',
            testMatch: /auth\.setup\.js/,
        },
        {
            name: 'admin',
            testMatch: /admin\/.*\.spec\.js/,
            dependencies: ['setup'],
            use: {
                ...devices['Desktop Chrome'],
                // Reuse the signed in session instead of logging in per test
                storageState: ADMIN_STORAGE_STATE,
            },
        },
        {
            name: 'shop',
            testMatch: /shop\/.*\.spec\.js/,
            use: { ...devices['Desktop Chrome'] },
        },
    ],
});
