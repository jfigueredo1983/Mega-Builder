import { expect, type Browser, type Page } from '@playwright/test';
import { E2E } from './env';

/** Open the studio through its back-office menu link (carries the admin token). */
export async function openStudio(page: Page) {
  await page.goto(E2E.adminPath);
  const href = await page.locator('a[href*="controller=AdminMegaBuilder"]').first().getAttribute('href');
  expect(href, 'Mega Builder menu link').toBeTruthy();
  await page.goto(href!);
  await expect(page.locator('#megabuilder-studio-app > *').first()).toBeVisible();
}

/** Call the studio API from inside the studio page, exactly like the Vue client does. */
export async function api(
  page: Page,
  action: string,
  payload: Record<string, unknown> = {},
  opts: { csrf?: boolean; method?: string } = {},
) {
  return page.evaluate(
    async ({ action, payload, opts }) => {
      const boot = (window as any).__MEGABUILDER_BOOT__;
      const sep = boot.ajaxUrl.includes('?') ? '&' : '?';
      const headers: Record<string, string> = { 'Content-Type': 'application/json' };
      if (opts.csrf !== false) headers['X-MB-CSRF-Token'] = boot.csrfToken;
      const res = await fetch(`${boot.ajaxUrl}${sep}ajax=1&mb_action=${action}`, {
        method: opts.method || 'POST',
        credentials: 'same-origin',
        headers,
        body: opts.method === 'GET' ? undefined : JSON.stringify({ action, ...payload }),
      });
      const json = await res.json().catch(() => ({}));
      return { status: res.status, ...json };
    },
    { action, payload, opts },
  );
}

/** A storefront page for an anonymous visitor (no back-office cookies). */
export async function visitorPage(browser: Browser, viewport = { width: 1440, height: 900 }) {
  const ctx = await browser.newContext({ storageState: { cookies: [], origins: [] }, viewport });
  return { ctx, page: await ctx.newPage() };
}

/** Horizontal overflow of the page, and Mega Builder elements that stick out of the viewport. */
export async function measureOverflow(page: Page, ignoreWithin = '[data-mb-component="slider"]') {
  return page.evaluate((ignore) => {
    const vw = document.documentElement.clientWidth;
    const offenders = [...document.querySelectorAll('.mb-storefront-root *')]
      .filter((el) => {
        const r = el.getBoundingClientRect();
        const s = getComputedStyle(el);
        return r.width > 0 && s.visibility !== 'hidden' && s.position !== 'fixed' && r.right > vw + 1 && !el.closest(ignore);
      })
      .slice(0, 5)
      .map((el) => `${el.tagName.toLowerCase()}.${[...el.classList].join('.')}`);
    return { pageScroll: document.documentElement.scrollWidth - vw, offenders };
  }, ignoreWithin);
}
