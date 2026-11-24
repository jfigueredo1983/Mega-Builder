import { expect, test, type Page } from '@playwright/test';
import { E2E, VIEWPORTS } from './env';

/** Open the studio through its back-office menu link (carries the admin token). */
async function openStudio(page: Page) {
  await page.goto(E2E.adminPath);
  const href = await page.locator('a[href*="controller=AdminMegaBuilder"]').first().getAttribute('href');
  expect(href, 'Mega Builder menu link').toBeTruthy();
  await page.goto(href!);
  await expect(page.locator('#megabuilder-studio-app > *').first()).toBeVisible();
}

/** Call the studio API from inside the studio page, exactly like the Vue client does. */
async function api(page: Page, action: string, payload: Record<string, unknown> = {}, opts: { csrf?: boolean; method?: string } = {}) {
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

test.describe.serial('Mega Builder studio → storefront', () => {
  const marker = `MB-E2E-${Date.now()}`;
  let publishedId = 0;

  test('studio loads with the full widget library', async ({ page }) => {
    await openStudio(page);
    const boot = await page.evaluate(() => (window as any).__MEGABUILDER_BOOT__);
    expect(boot.widgets).toHaveLength(77);
    expect(boot.csrfToken).toMatch(/^[a-f0-9]{64}$/);
    expect(Object.keys(boot.pageSections)).toHaveLength(15);
  });

  test('drag a heading onto the canvas, edit it and publish', async ({ page }) => {
    await openStudio(page);
    await page.getByRole('button', { name: /Live Visual Canvas/ }).click();

    const chip = page.locator('.mb-widget-chip').filter({ has: page.locator('span', { hasText: /^Heading$/ }) });
    const column = page.locator('.mb-canvas-column').first();
    await expect(chip).toHaveCount(1);
    await column.scrollIntoViewIfNeeded();

    // Fire the HTML5 drag events on the exact elements. Mouse-driven dragTo() can scroll the
    // palette between press and first move, so the browser starts the drag on a neighbouring chip.
    const dataTransfer = await page.evaluateHandle(() => new DataTransfer());
    await chip.dispatchEvent('dragstart', { dataTransfer });
    await column.dispatchEvent('dragenter', { dataTransfer });
    await column.dispatchEvent('dragover', { dataTransfer });
    await column.dispatchEvent('drop', { dataTransfer });
    await chip.dispatchEvent('dragend', { dataTransfer });
    await expect(page.getByText('Added "Heading" to canvas.')).toBeVisible();

    const headline = page.locator('.mb-inspector-card').filter({ hasText: 'Main Headline or Text' }).locator('input').first();
    await expect(headline).toHaveValue('Crafted for Modern Living');
    await headline.fill(marker);
    await headline.press('Tab');
    await expect(page.locator('.mb-canvas-column').first()).toContainText(marker);

    const saved = page.waitForResponse((r) => r.url().includes('mb_action=saveTemplate'));
    await page.getByRole('button', { name: /Publish Live/ }).click();
    const body = await (await saved).json();
    expect(body.ok, body.message).toBe(true);
    expect(body.data.template.active).toBe(1);
    publishedId = body.data.template.id_template;
    expect(publishedId).toBeGreaterThan(0);
  });

  for (const vp of VIEWPORTS) {
    test(`published heading shows on the storefront at ${vp.name} (${vp.width}px) without overflow`, async ({ browser }, testInfo) => {
      test.skip(!publishedId, 'publish step failed');
      // Fresh visitor: no back-office cookies.
      const ctx = await browser.newContext({ storageState: { cookies: [], origins: [] }, viewport: { width: vp.width, height: vp.height } });
      const page = await ctx.newPage();
      await page.goto('/');

      const block = page.getByText(marker, { exact: true });
      await expect(block).toBeVisible();
      await expect(page.locator('.mb-hook-wrapper[data-mb-hook="displayHome"]')).toHaveCount(1);

      const overflow = await page.evaluate(() => {
        const vw = document.documentElement.clientWidth;
        const offenders = [...document.querySelectorAll('.mb-storefront-root *')]
          .filter((el) => {
            const r = el.getBoundingClientRect();
            const style = getComputedStyle(el);
            return r.width > 0 && style.visibility !== 'hidden' && r.right > vw + 1 && !el.closest('[data-mb-component="slider"]');
          })
          .slice(0, 5)
          .map((el) => `${el.tagName.toLowerCase()}.${[...el.classList].join('.')}`);
        return { pageScroll: document.documentElement.scrollWidth - vw, offenders };
      });
      await testInfo.attach(`storefront-${vp.name}.png`, { body: await page.screenshot({ fullPage: false }), contentType: 'image/png' });

      expect(overflow.offenders, 'Mega Builder elements wider than the viewport').toEqual([]);
      expect(overflow.pageScroll, 'horizontal page scroll').toBeLessThanOrEqual(1);
      await ctx.close();
    });
  }

  test('published template does not leak onto other page types', async ({ browser }) => {
    test.skip(!publishedId, 'publish step failed');
    const ctx = await browser.newContext({ storageState: { cookies: [], origins: [] } });
    const page = await ctx.newPage();
    await page.goto('/index.php?controller=new-products');
    await expect(page.getByText(marker, { exact: true })).toHaveCount(0);
    await ctx.close();
  });

  test('draft preview needs a signed link', async ({ page, playwright }) => {
    test.skip(!publishedId, 'publish step failed');
    await openStudio(page);

    const anon = await playwright.request.newContext({ baseURL: E2E.baseURL });
    const preview = `/index.php?fc=module&module=megabuilder&controller=preview&id_template=${publishedId}`;
    for (const url of [preview, `${preview}&mb_token=1999999999.${'a'.repeat(64)}`]) {
      const res = await anon.get(url, { maxRedirects: 0 });
      expect(res.status(), url).toBe(302);
      expect(res.headers()['location']).toContain('404');
    }

    const link = await api(page, 'getPreviewLink', { id_template: publishedId });
    expect(link.status).toBe(200);
    const ok = await anon.get(link.data.url, { maxRedirects: 0 });
    expect(ok.status()).toBe(200);
    expect(await ok.text()).toContain(marker);
    await anon.dispose();
  });

  test('studio API rejects requests without the CSRF header or as GET', async ({ page }) => {
    await openStudio(page);
    expect((await api(page, 'listTemplates', {}, { csrf: false })).status).toBe(403);
    expect((await api(page, 'listTemplates', {}, { method: 'GET' })).status).toBe(403);
    expect((await api(page, 'notARealAction')).status).toBe(400);
    expect((await api(page, 'listTemplates')).status).toBe(200);
  });

  test.afterAll(async ({ browser }) => {
    if (!publishedId) return;
    const ctx = await browser.newContext();
    const page = await ctx.newPage();
    await openStudio(page);
    const res = await api(page, 'deleteTemplate', { id_template: publishedId });
    expect(res.status).toBe(200);
    await ctx.close();
  });
});
