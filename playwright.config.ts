import { defineConfig } from '@playwright/test';

const authFile = 'tests/e2e/.auth/owner.json';
const hasCredentials = Boolean(
    process.env.E2E_EMAIL && process.env.E2E_PASSWORD,
);

export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: false,
    workers: 1,
    retries: 1,
    reporter: process.env.CI ? 'github' : 'list',
    use: {
        baseURL: process.env.E2E_BASE_URL ?? 'http://localhost:8000',
        trace: 'on-first-retry',
    },
    projects: hasCredentials
        ? [
              { name: 'setup', testMatch: /auth\.setup\.ts/ },
              {
                  name: 'chromium',
                  testIgnore: /auth\.setup\.ts|smoke\.spec\.ts/,
                  use: { storageState: authFile },
                  dependencies: ['setup'],
              },
              {
                  name: 'smoke',
                  testMatch: /smoke\.spec\.ts/,
                  use: { storageState: { cookies: [], origins: [] } },
              },
          ]
        : [{ name: 'chromium' }],
});
