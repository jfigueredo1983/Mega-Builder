import type { BootPayload, LayoutTree, WidgetDefinition } from '../../ui/types';

export function makeLayout(): LayoutTree {
  return {
    version: '1.0',
    sections: [
      {
        id: 'sec_a',
        label: 'Hero',
        layout_mode: 'boxed',
        max_width: 1280,
        gap: { desktop: 24, tablet: 18, mobile: 14 },
        padding_y: { desktop: 36, tablet: 28, mobile: 20 },
        padding_x: { desktop: 20, tablet: 16, mobile: 14 },
        bg_color: 'transparent',
        bg_image: '',
        reverse_mobile: false,
        hide_desktop: false,
        hide_tablet: false,
        hide_mobile: false,
        columns: [
          {
            id: 'col_a1',
            width: 100,
            width_tablet: 100,
            width_mobile: 100,
            align_items: 'stretch',
            padding: { desktop: 12, tablet: 10, mobile: 8 },
            bg_color: 'transparent',
            border_radius: 0,
            widgets: [],
          },
        ],
      },
    ],
  } as unknown as LayoutTree;
}

const widgets: WidgetDefinition[] = [
  { type: 'heading', category: 'basic', label: 'Heading', icon: 'type', defaults: { text: 'Hello', subtitle: 'EYEBROW' } },
  { type: 'button', category: 'basic', label: 'Button CTA', icon: 'mouse-pointer', defaults: { text: 'Shop', url: '#catalog' } },
  { type: 'breadcrumb', category: 'site', label: 'Breadcrumb', icon: 'route', defaults: {}, status: 'coming_soon' },
  { type: 'template_widget', category: 'premium', label: 'Template', icon: 'box', defaults: {} },
] as unknown as WidgetDefinition[];

export function makeBoot(overrides: Partial<BootPayload> = {}): BootPayload {
  return {
    version: '1.0.0',
    psVersion: '9.2.0',
    shopName: 'Test Shop',
    storefrontUrl: 'https://shop.test/',
    ajaxUrl: 'https://shop.test/admin/index.php?controller=AdminMegaBuilder&token=abc',
    previewUrl: 'https://shop.test/module/megabuilder/preview',
    csrfToken: 'c'.repeat(64),
    idLang: 1,
    idShop: 1,
    initialSection: '',
    initialTemplate: 0,
    pageSections: { homepage: { label: 'Homepage', default_hook: 'displayHome' } },
    subComponents: { slider: { label: 'Sliders Builder', default_hook: 'displayHome' } },
    allowedHooks: ['displayHome'],
    widgets,
    blueprints: {
      homepage: { entity_kind: 'page_template', section_type: 'homepage', title: 'Home', hook_name: 'displayHome', layout: makeLayout() },
    },
    templates: [],
    globalSettings: { colors: { primary: '#123456' } } as unknown as BootPayload['globalSettings'],
    studioTheme: {} as BootPayload['studioTheme'],
    media: { folders: ['icons'], files: [] },
    ...overrides,
  };
}
