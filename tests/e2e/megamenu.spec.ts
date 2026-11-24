import { expect, test } from '@playwright/test';
import { api, measureOverflow, openStudio, visitorPage } from './helpers';

/**
 * Publishes a header template with two MegaMenus (custom items + live store categories)
 * and drives them like a shopper would: mouse, keyboard and the mobile drawer.
 */
const MANUAL = '#mbmm-w_mm_manual';
const CATS = '#mbmm-w_mm_cats';

const manualItems = [
  {
    title: 'Living', url: '/living', badge: 'NEW',
    columns: [
      { heading: 'Seating', url: '/seating', links: [{ title: 'Sofas E2E', url: '/sofas' }, { title: 'Chairs', url: '/chairs' }] },
      { heading: 'Tables', url: '', links: [{ title: 'Coffee tables', url: '/coffee' }] },
    ],
    promo_title: 'Spring sale', promo_url: '/sale',
  },
  { title: 'Dining', url: '/dining', columns: [{ heading: 'Dining', url: '', links: [{ title: 'Dining tables', url: '/dt' }] }] },
  { title: 'Contact', url: '/contact', columns: [] },
];

const widget = (id: string, content: Record<string, unknown>) => ({ id, type: 'nav_megamenu', label: 'Menu', content });

test.describe.serial('MegaMenu on the storefront', () => {
  let templateId = 0;

  test.beforeAll(async ({ browser }) => {
    const ctx = await browser.newContext();
    const page = await ctx.newPage();
    await openStudio(page);
    const res = await api(page, 'saveTemplate', {
      id_template: 0,
      entity_kind: 'page_template',
      section_type: 'header',
      hook_name: 'displayNavFullWidth',
      active: 1,
      title: 'E2E MegaMenu header',
      layout: {
        sections: [
          { id: 'sec_mm1', columns: [{ id: 'col_mm1', widgets: [widget('w_mm_manual', { source: 'manual', menu_label: 'Main menu', items: manualItems })] }] },
          { id: 'sec_mm2', columns: [{ id: 'col_mm2', widgets: [widget('w_mm_cats', { source: 'categories', max_items: 4, menu_label: 'Departments' })] }] },
        ],
      },
    });
    expect(res.ok, res.message).toBe(true);
    templateId = res.data.template.id_template;
    await ctx.close();
  });

  test.afterAll(async ({ browser }) => {
    if (!templateId) return;
    const ctx = await browser.newContext();
    const page = await ctx.newPage();
    await openStudio(page);
    expect((await api(page, 'deleteTemplate', { id_template: templateId })).status).toBe(200);
    await ctx.close();
  });

  test('desktop: hovering an item opens its panel, moving away closes it', async ({ browser }) => {
    const { ctx, page } = await visitorPage(browser);
    await page.goto('/');
    const nav = page.locator(MANUAL);
    await expect(nav).toHaveClass(/is-js/);
    await expect(nav).not.toHaveClass(/is-mobile/);
    await expect(nav.locator('.mb-mm-burger')).toBeHidden();

    const living = nav.locator('.mb-mm-item').first();
    const panel = living.locator('.mb-mm-panel');
    await expect(panel).toBeHidden();

    await living.locator('.mb-mm-link').hover();
    await expect(panel).toBeVisible();
    await expect(panel.getByRole('link', { name: 'Sofas E2E' })).toHaveAttribute('href', /\/sofas$/);
    await expect(panel.locator('.mb-mm-promo')).toContainText('Spring sale');
    await expect(living.locator('.mb-mm-toggle')).toHaveAttribute('aria-expanded', 'true');

    await page.mouse.move(5, 850);
    await expect(panel).toBeHidden();
    await ctx.close();
  });

  test('desktop: items without columns have no toggle', async ({ browser }) => {
    const { ctx, page } = await visitorPage(browser);
    await page.goto('/');
    const contact = page.locator(MANUAL).locator('.mb-mm-item', { hasText: 'Contact' });
    await expect(contact.locator('.mb-mm-toggle')).toHaveCount(0);
    await expect(contact.locator('.mb-mm-link')).toHaveAttribute('href', /\/contact$/);
    await ctx.close();
  });

  test('desktop: keyboard opens, enters, moves between and closes panels', async ({ browser }) => {
    const { ctx, page } = await visitorPage(browser);
    await page.goto('/');
    const nav = page.locator(MANUAL);
    const items = nav.locator('.mb-mm-item');
    const toggle = items.first().locator('.mb-mm-toggle');

    await toggle.focus();
    await page.keyboard.press('Enter');
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    await expect(items.first().locator('.mb-mm-panel')).toBeVisible();

    await page.keyboard.press('Escape');
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await expect(toggle).toBeFocused();

    await page.keyboard.press('ArrowDown');
    await expect(items.first().locator('.mb-mm-panel a').first()).toBeFocused();

    await items.first().locator('.mb-mm-link').focus();
    await page.keyboard.press('ArrowRight');
    await expect(items.nth(1).locator('.mb-mm-link')).toBeFocused();
    await expect(items.first().locator('.mb-mm-panel')).toBeHidden();
    await ctx.close();
  });

  test('desktop: clicking outside closes an open panel', async ({ browser }) => {
    const { ctx, page } = await visitorPage(browser);
    await page.goto('/');
    const first = page.locator(MANUAL).locator('.mb-mm-item').first();
    await first.locator('.mb-mm-toggle').click();
    await expect(first.locator('.mb-mm-panel')).toBeVisible();
    await page.locator('body').click({ position: { x: 5, y: 5 } });
    await expect(first.locator('.mb-mm-panel')).toBeHidden();
    await ctx.close();
  });

  test('store-category menu lists live categories with real links', async ({ browser }) => {
    const { ctx, page } = await visitorPage(browser);
    await page.goto('/');
    const links = page.locator(CATS).locator('.mb-mm-bar > .mb-mm-item > .mb-mm-link');
    const count = await links.count();
    expect(count, 'top-level store categories').toBeGreaterThan(0);
    expect(count).toBeLessThanOrEqual(4);
    for (let i = 0; i < count; i++) {
      await expect(links.nth(i)).toHaveAttribute('href', /^https?:\/\/[^#]+$/);
      await expect(links.nth(i)).not.toHaveText('');
    }
    await ctx.close();
  });

  test('mobile: burger opens the drawer, items expand, Escape closes, no overflow', async ({ browser }) => {
    const { ctx, page } = await visitorPage(browser, { width: 390, height: 844 });
    await page.goto('/');
    const nav = page.locator(MANUAL);
    const burger = nav.locator('.mb-mm-burger');
    const drawer = nav.locator('.mb-mm-drawer');

    await expect(nav).toHaveClass(/is-mobile/);
    await expect(burger).toBeVisible();
    await expect(drawer).toBeHidden();
    expect(await measureOverflow(page)).toEqual({ pageScroll: expect.any(Number), offenders: [] });
    expect((await measureOverflow(page)).pageScroll).toBeLessThanOrEqual(1);

    await burger.click();
    await expect(burger).toHaveAttribute('aria-expanded', 'true');
    await expect(drawer).toBeVisible();
    await expect(nav.locator('.mb-mm-close')).toBeFocused();
    const box = await drawer.boundingBox();
    expect(box && box.x >= 0 && box.x + box.width <= 390).toBe(true);

    const living = nav.locator('.mb-mm-item').first();
    await living.locator('.mb-mm-toggle').click();
    await expect(living.getByRole('link', { name: 'Sofas E2E' })).toBeVisible();
    await expect(nav.locator('.mb-mm-item').nth(1).locator('.mb-mm-link')).toBeVisible();

    await page.keyboard.press('Escape'); // closes the open item first
    await expect(living.locator('.mb-mm-toggle')).toHaveAttribute('aria-expanded', 'false');
    await page.keyboard.press('Escape'); // then the drawer
    await expect(drawer).toBeHidden();
    await expect(burger).toHaveAttribute('aria-expanded', 'false');
    await expect(burger).toBeFocused();
    await ctx.close();
  });

  test('mobile: tapping the overlay closes the drawer', async ({ browser }) => {
    const { ctx, page } = await visitorPage(browser, { width: 390, height: 844 });
    await page.goto('/');
    const nav = page.locator(MANUAL);
    await nav.locator('.mb-mm-burger').click();
    await expect(nav.locator('.mb-mm-drawer')).toBeVisible();
    await page.mouse.click(380, 400); // right of the 86vw drawer, on the overlay
    await expect(nav.locator('.mb-mm-drawer')).toBeHidden();
    await ctx.close();
  });
});
