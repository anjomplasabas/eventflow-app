import { defineConfig, devices } from '@playwright/test';

/**
 * EventFlow end-to-end tests. These run against a locally-booted Laravel
 * dev server with a freshly-seeded SQLite database. To run:
 *
 *   npm run test:e2e
 *
 * The webServer entry below boots `php artisan serve` and the Vite build is
 * expected to be in place (so run `npm run build` first, or point VITE_DEV
 * at a running dev server).
 */
export default defineConfig({
    testDir: './tests/e2e',
    timeout: 30_000,
    fullyParallel: false,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    reporter: process.env.CI ? 'github' : 'list',
    use: {
        baseURL: process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8000',
        trace: 'retain-on-failure',
    },
    projects: [
        {
            name: 'chromium',
            use: devices['Desktop Chrome'],
        },
    ],
    webServer: process.env.E2E_SKIP_WEB_SERVER
        ? undefined
        : {
              command:
                  'php artisan migrate:fresh --seed --quiet && php artisan serve --port=8000',
              port: 8000,
              reuseExistingServer: !process.env.CI,
              timeout: 60_000,
          },
});
