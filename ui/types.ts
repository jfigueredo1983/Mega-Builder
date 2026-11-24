/**
 * Mega Builder — Stage 6 TypeScript Domain Contracts
 */

export type BreakpointMode = 'desktop' | 'tablet' | 'mobile';

export interface ResponsiveNumber {
  desktop: number;
  tablet: number;
  mobile: number;
}

export interface ResponsiveAlign {
  desktop: 'left' | 'center' | 'right' | 'justify';
  tablet: 'left' | 'center' | 'right' | 'justify';
  mobile: 'left' | 'center' | 'right' | 'justify';
}

export interface WidgetItemEntry {
  title?: string;
  subtitle?: string;
  answer?: string;
  content?: string;
  badge?: string;
  cta_text?: string;
  cta_url?: string;
  src?: string;
  url?: string;
  x?: number;
  y?: number;
}

export interface WidgetNode {
  id: string;
  type: string;
  label: string;
  content: Record<string, any>;
  style: {
    text_color: string;
    bg_color: string;
    accent_color: string;
    border_color: string;
    font_size: ResponsiveNumber;
    font_weight: number;
    line_height: number;
    text_align: ResponsiveAlign;
    border_radius: number;
    border_width: number;
    columns_count: ResponsiveNumber;
    shadow: 'none' | 'sm' | 'md' | 'lg' | 'xl';
  };
  advanced: {
    padding_y: ResponsiveNumber;
    padding_x: ResponsiveNumber;
    margin_y: ResponsiveNumber;
    z_index: number;
    hide_desktop: boolean;
    hide_tablet: boolean;
    hide_mobile: boolean;
    custom_class: string;
    custom_css: string;
  };
}

export interface ColumnNode {
  id: string;
  width: number;
  width_tablet: number;
  width_mobile: number;
  align_items: 'flex-start' | 'center' | 'flex-end' | 'stretch';
  padding: ResponsiveNumber;
  bg_color: string;
  border_radius: number;
  widgets: WidgetNode[];
}

export interface SectionNode {
  id: string;
  label: string;
  layout_mode: 'boxed' | 'full_width' | 'grid';
  max_width: number;
  gap: ResponsiveNumber;
  padding_y: ResponsiveNumber;
  padding_x: ResponsiveNumber;
  bg_color: string;
  bg_image: string;
  reverse_mobile: boolean;
  hide_desktop: boolean;
  hide_tablet: boolean;
  hide_mobile: boolean;
  columns: ColumnNode[];
}

export interface LayoutTree {
  version: string;
  sections: SectionNode[];
  custom_css?: string;
}

export interface RevisionSummary {
  id_revision: number;
  id_employee: number;
  label: string;
  date_add: string;
}

export interface TemplateRecord {
  id_template: number;
  entity_kind: 'page_template' | 'sub_component';
  section_type: string;
  hook_name: string;
  scope_rule: 'all' | 'specific_ids' | 'conditions';
  scope_targets: number[];
  active: number;
  priority: number;
  title: string;
  sections_count?: number;
  revision_count?: number;
  layout?: LayoutTree;
  compiled_html?: string;
  compiled_css?: string;
  revisions?: RevisionSummary[];
  date_add?: string;
  date_upd?: string;
}

export interface WidgetDefinition {
  type: string;
  category: 'basic' | 'general' | 'premium' | 'site' | 'catalog' | 'product' | 'listing_customer';
  label: string;
  icon: string;
  defaults: Record<string, any>;
}

export interface GlobalSettings {
  colors: {
    primary: string;
    secondary: string;
    accent: string;
    surface: string;
    surface_alt: string;
    text: string;
    muted: string;
    border: string;
  };
  typography: {
    heading_font: string;
    body_font: string;
    base_size_px: number;
    scale_ratio: number;
    fluid_clamp: boolean;
  };
  layout: {
    container_width_px: number;
    section_gap_px: number;
    card_radius_px: number;
    tablet_breakpoint_px: number;
    mobile_breakpoint_px: number;
  };
  components: {
    button_style: 'sharp' | 'rounded' | 'pill';
    card_elevation: 'none' | 'sm' | 'md' | 'lg';
    touch_min_px: number;
  };
  lightbox: {
    enabled: boolean;
    zoom_on_tap: boolean;
    backdrop: string;
  };
  performance: {
    precompile_css: boolean;
    lazy_load_images: boolean;
    front_admin_bar: boolean;
    admin_direct_buttons: boolean;
  };
  page_rules: Record<string, { enabled: boolean; replace_mode: 'augment' | 'replace'; hook_name: string }>;
  design_md: string;
}

export interface StudioTheme {
  preset: 'obsidian_pro' | 'nordic_light' | 'emerald_craft' | 'royal_velvet';
  mode: 'light' | 'dark';
  density: 'compact' | 'comfortable' | 'spacious';
  radius_px: number;
  brand_title: string;
  brand_logo: string;
  accent_color: string;
}

export interface MediaAsset {
  name: string;
  folder: string;
  rel_path: string;
  url: string;
  ext: string;
  size_bytes: number;
  updated_at?: string;
}

export interface BootPayload {
  version: string;
  psVersion: string;
  shopName: string;
  storefrontUrl: string;
  ajaxUrl: string;
  previewUrl: string;
  csrfToken: string;
  idLang: number;
  idShop: number;
  initialSection: string;
  initialTemplate: number;
  pageSections: Record<string, { label: string; default_hook: string }>;
  subComponents: Record<string, { label: string; default_hook: string }>;
  allowedHooks: string[];
  widgets: WidgetDefinition[];
  blueprints: Record<string, { entity_kind: 'page_template' | 'sub_component'; section_type: string; title: string; hook_name: string; layout: LayoutTree }>;
  templates: TemplateRecord[];
  globalSettings: GlobalSettings;
  studioTheme: StudioTheme;
  media: { folders: string[]; files: MediaAsset[] };
}

export interface ApiEnvelope<T = Record<string, any>> {
  ok: boolean;
  code: string;
  message: string;
  data: T;
  errors: string[];
}
