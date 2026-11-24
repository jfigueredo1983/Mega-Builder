import { chromium, expect, type FullConfig } from '@playwright/test';
import fs from 'node:fs';
import path from 'node:path';
import { E2E } from './env';

/**
 * Sign in to the back office once and save the session for every test.
 * Works with both the PrestaShop 8 legacy login and the PrestaShop 9 Symfony login.
 */
export default async function globalSetup(_config: FullConfig) {
  // Same path as `use.storageState` in playwright.config.ts (relative to the repo root).
  const statePath = path.resolve('tests/e2e/.auth/admin.json');
  fs.mkdirSync(path.dirname(statePath), { recursive: true });

  const browser = await chromium.launch();
  const page = await browser.newPage({ baseURL: E2E.baseURL });
  await page.goto(E2E.adminPath);

  const email = page.locator('#email, input[name="_username"], input[name="email"], input[type="email"]').first();
  const password = page.locator('#passwd, input[name="_password"], input[name="passwd"], input[type="password"]').first();
  await email.fill(E2E.email);
  await password.fill(E2E.password);
  await page.locator('#submit_login, button[type="submit"]').first().click();

  // Any back-office page that is not the login screen means we are in.
  await expect(page).not.toHaveURL(/login/i, { timeout: 30_000 });
  await expect(page.locator('a[href*="controller=AdminMegaBuilder"]').first()).toBeAttached({ timeout: 30_000 });

  await page.context().storageState({ path: statePath });
  await browser.close();
}
