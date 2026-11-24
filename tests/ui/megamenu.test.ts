import { describe, expect, it } from 'vitest';
import { MAX_ITEMS, MAX_LINKS, formatLinks, normalizeItems, parseLinks } from '../../ui/megamenu';

describe('MegaMenu link text format', () => {
  it('parses "Label | /url" lines, trimming and dropping empty labels', () => {
    expect(parseLinks('Sofas | /sofas\n  Chairs  \n | /orphan\n\nTables|/tables')).toEqual([
      { title: 'Sofas', url: '/sofas' },
      { title: 'Chairs', url: '' },
      { title: 'Tables', url: '/tables' },
    ]);
  });

  it('keeps everything after the first bar as the URL', () => {
    expect(parseLinks('Search | /search?q=a|b')).toEqual([{ title: 'Search', url: '/search?q=a|b' }]);
  });

  it('caps the number of links', () => {
    const many = Array.from({ length: 30 }, (_, i) => `L${i} | /l${i}`).join('\n');
    expect(parseLinks(many)).toHaveLength(MAX_LINKS);
  });

  it('round-trips through formatLinks', () => {
    const links = [
      { title: 'Sofas', url: '/sofas' },
      { title: 'Chairs', url: '' },
    ];
    expect(formatLinks(links)).toBe('Sofas | /sofas\nChairs');
    expect(parseLinks(formatLinks(links))).toEqual(links);
  });
});

describe('MegaMenu item normalisation', () => {
  it('converts the 1.0 "answer" format into one column of entries', () => {
    const [item] = normalizeItems([{ title: 'Living', url: '/x', answer: 'Sofas • Tables, Chairs' }]);
    expect(item.columns).toHaveLength(1);
    expect(item.columns[0].links.map((l) => l.title)).toEqual(['Sofas', 'Tables', 'Chairs']);
  });

  it('fills missing fields, drops untitled items and treats "#" promo image as none', () => {
    const items = normalizeItems([{ title: 'A', promo_image: '#' }, { title: '' }, null, 'x']);
    expect(items).toEqual([
      { title: 'A', url: '', badge: '', columns: [], promo_image: '', promo_title: '', promo_url: '' },
    ]);
  });

  it('caps items and ignores non-arrays', () => {
    expect(normalizeItems(Array.from({ length: 40 }, (_, i) => ({ title: `I${i}` })))).toHaveLength(MAX_ITEMS);
    expect(normalizeItems({ title: 'x' })).toEqual([]);
  });
});
