/**
 * MegaMenu data helpers shared by the studio editor and canvas preview.
 * Mirrors MegaMenuRenderer::normalizeItems() on the PHP side.
 */

export interface MenuLink {
  title: string;
  url: string;
}

export interface MenuColumn {
  heading: string;
  url: string;
  links: MenuLink[];
}

export interface MenuItem {
  title: string;
  url: string;
  badge: string;
  columns: MenuColumn[];
  promo_image: string;
  promo_title: string;
  promo_url: string;
}

export const MAX_ITEMS = 12;
export const MAX_COLUMNS = 4;
export const MAX_LINKS = 12;

const str = (v: unknown): string => (typeof v === 'string' || typeof v === 'number' ? String(v).trim() : '');

/** "Label | /url" per line → links. Lines without a label are dropped; a missing URL stays empty. */
export function parseLinks(text: string): MenuLink[] {
  return text
    .split(/\r?\n/)
    .map((line) => {
      const bar = line.indexOf('|');
      const title = (bar === -1 ? line : line.slice(0, bar)).trim();
      const url = bar === -1 ? '' : line.slice(bar + 1).trim();
      return { title, url };
    })
    .filter((l) => l.title !== '')
    .slice(0, MAX_LINKS);
}

export function formatLinks(links: MenuLink[] | undefined): string {
  return (links || []).map((l) => (l.url ? `${l.title} | ${l.url}` : l.title)).join('\n');
}

/** Accepts the 1.0 shape ({title, url, answer: "A • B"}) and fills every field. */
export function normalizeItems(items: unknown): MenuItem[] {
  if (!Array.isArray(items)) return [];
  const out: MenuItem[] = [];
  for (const raw of items.slice(0, MAX_ITEMS)) {
    if (!raw || typeof raw !== 'object') continue;
    const it = raw as Record<string, any>;
    const title = str(it.title);
    if (!title) continue;

    let columns: MenuColumn[] = (Array.isArray(it.columns) ? it.columns : [])
      .slice(0, MAX_COLUMNS)
      .filter((c: unknown) => c && typeof c === 'object')
      .map((c: Record<string, any>) => ({
        heading: str(c.heading),
        url: str(c.url),
        links: (Array.isArray(c.links) ? c.links : [])
          .filter((l: any) => l && str(l.title))
          .slice(0, MAX_LINKS)
          .map((l: any) => ({ title: str(l.title), url: str(l.url) })),
      }))
      .filter((c: MenuColumn) => c.heading !== '' || c.links.length > 0);

    if (columns.length === 0 && str(it.answer)) {
      columns = [
        {
          heading: '',
          url: '',
          links: str(it.answer)
            .split(/\s*[•|,]\s*/u)
            .map((t) => t.trim())
            .filter(Boolean)
            .slice(0, MAX_LINKS)
            .map((title) => ({ title, url: '' })),
        },
      ];
    }

    const promoImage = str(it.promo_image);
    out.push({
      title,
      url: str(it.url),
      badge: str(it.badge),
      columns,
      promo_image: promoImage === '#' ? '' : promoImage,
      promo_title: str(it.promo_title),
      promo_url: str(it.promo_url),
    });
  }
  return out;
}

export function emptyItem(n: number): MenuItem {
  return { title: `Menu item ${n}`, url: '', badge: '', columns: [], promo_image: '', promo_title: '', promo_url: '' };
}

export function emptyColumn(): MenuColumn {
  return { heading: 'New column', url: '', links: [] };
}
