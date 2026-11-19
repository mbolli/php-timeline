import { defineConfig } from '@playwright/test';
import { e2eDir, e2ePort } from './tests/e2e/support/env';

// The tests run their own server on their own port with a freshly seeded database in the temp
// directory, so data/timeline.db and a running dev server stay untouched.
export default defineConfig({
    testDir: 'tests/e2e',
    outputDir: 'test-results',
    globalTeardown: './tests/e2e/support/teardown.ts',
    // One server and one database for all tests
    fullyParallel: false,
    workers: 1,
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    reporter: [['list']],
    timeout: 30_000,
    expect: { timeout: 5_000 },
    use: {
        baseURL: `http://127.0.0.1:${e2ePort}`,
        browserName: 'chromium',
        headless: true,
        viewport: { width: 1280, height: 800 },
        launchOptions: {
            executablePath: process.env.E2E_CHROMIUM ?? '/usr/bin/chromium',
        },
        trace: 'retain-on-failure',
    },
    webServer: {
        // Started directly instead of through `composer serve`, which Composer stops after 300 seconds
        command: `php bin/seed.php --force "${e2eDir}/timeline.db" && exec php vendor/bin/laminas mezzio:swoole:start`,
        url: `http://127.0.0.1:${e2ePort}/`,
        reuseExistingServer: false,
        timeout: 30_000,
        stdout: 'ignore',
        stderr: 'pipe',
        gracefulShutdown: { signal: 'SIGTERM', timeout: 5_000 },
        env: {
            APP_ENV: 'production',
            TIMELINE_PORT: String(e2ePort),
            TIMELINE_DB: `${e2eDir}/timeline.db`,
            TIMELINE_PID_FILE: `${e2eDir}/swoole.pid`,
        },
    },
});
