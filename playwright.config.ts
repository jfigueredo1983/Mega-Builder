import { defineConfig, devices } from '@playwright/test';

/**
 * End-to-end tests against a running PrestaShop store with Mega Builder installed
 * (see tests/e2e/setup-store.sh). Defaults match the disposable Docker store.
 */
export default defineConfig({
  testDir: 'tests/e2e',
  timeout: 90_000,
  expect: { timeout: 15_000 },
  fullyParallel: false,
  workers: 1,
  retries: process.env.CI ? 1 : 0,
  reporter: process.env.CI ? [['list'], ['html', { open: 'never' }]] : 'list',
  globalSetup: './tests/e2e/global-setup.ts',
  use: {
    baseURL: process.env.MB_E2E_BASE_URL || 'http://localhost:8080',
    storageState: 'tests/e2e/.auth/admin.json',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
});
