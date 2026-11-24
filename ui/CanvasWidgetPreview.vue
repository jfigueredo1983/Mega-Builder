<script setup lang="ts">
import { computed } from 'vue';
import type { BreakpointMode, WidgetNode } from './types';
import { normalizeItems, type MenuItem } from './megamenu';

const props = defineProps<{
  widget: WidgetNode;
  viewport: BreakpointMode;
  /** Store category tree, shown for MegaMenus whose source is "categories". */
  categoryMenu?: MenuItem[];
}>();

const menuItems = computed<MenuItem[]>(() => {
  if (props.widget.type !== 'nav_megamenu') return [];
  if (props.widget.content.source === 'categories') {
    return (props.categoryMenu || []).slice(0, Number(props.widget.content.max_items) || 8);
  }
  return normalizeItems(props.widget.content.items);
});

const SAMPLE_PRODUCTS = [
  {
    title: 'Verona Cloud Modular Bouclé Sofa',
    category: 'Living Room',
    price: '$1,890.00',
    badge: 'BESTSELLER',
    img: 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?auto=format&fit=crop&w=600&q=80',
  },
  {
    title: 'Nordic Solid Smoked Oak Dining Table',
    category: 'Dining Room',
    price: '$1,450.00',
    badge: 'SOLID OAK',
    img: 'https://images.unsplash.com/photo-1615873968403-89e068629265?auto=format&fit=crop&w=600&q=80',
  },
  {
    title: 'Aurelia Curved Cognac Lounge Chair',
    category: 'Accent Seating',
    price: '$780.00',
    badge: 'NEW ARRIVAL',
    img: 'https://images.unsplash.com/photo-1586023492125-27b2c045efd7?auto=format&fit=crop&w=600&q=80',
  },
  {
    title: 'Sanctuary Upholstered King Bed Frame',
    category: 'Bedroom',
    price: '$1,620.00',
    badge: 'LIMITED EDITION',
    img: 'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=600&q=80',
  },
];

const SHADOW_MAP: Record<string, string> = {
  none: 'none',
  sm: '0 2px 8px rgba(15, 23, 42, 0.06)',
  md: '0 8px 20px rgba(15, 23, 42, 0.10)',
  lg: '0 16px 34px rgba(15, 23, 42, 0.14)',
  xl: '0 24px 48px rgba(37, 99, 235, 0.18)',
};

const containerStyle = computed(() => {
  const s = props.widget.style;
  const adv = props.widget.advanced;
  const vp = props.viewport;

  const py = adv.padding_y?.[vp] ?? 12;
  const px = adv.padding_x?.[vp] ?? 14;
  const fs = s.font_size?.[vp] ?? 16;
  const align = s.text_align?.[vp] ?? 'left';

  return {
    color: s.text_color || '#0f172a',
    backgroundColor:
      s.bg_color && s.bg_color !== 'transparent' ? s.bg_color : 'transparent',
    borderRadius: (s.border_radius ?? 10) + 'px',
    border:
      (s.border_width ?? 0) > 0
        ? `${s.border_width}px solid ${s.border_color || '#e2e8f0'}`
        : 'none',
    padding: `${py}px ${px}px`,
    textAlign: align as any,
    fontSize: `${fs}px`,
    fontWeight: s.font_weight || 500,
    lineHeight: String(s.line_height || 1.5),
    boxShadow: SHADOW_MAP[s.shadow || 'none'] || 'none',
  };
});

const activeCols = computed(() => {
  const raw = props.widget.style.columns_count?.[props.viewport] ?? 4;
  if (props.viewport === 'mobile') return 1;
  if (props.viewport === 'tablet') return Math.min(2, raw);
  return Math.max(1, Math.min(6, raw));
});

function formatDynamicText(val: any, fallback = ''): string {
  const str = typeof val === 'string' && val.trim() ? val : fallback;
  return str
    .replace(/\{\{\s*mb:shop_name\s*\}\}/gi, 'DoIt Architectural Furniture')
    .replace(/\{\{\s*mb:product_name\s*\}\}/gi, 'Verona Cloud Modular Bouclé Sofa')
    .replace(/\{\{\s*mb:product_price\s*\}\}/gi, '$1,890.00')
    .replace(/\{\{\s*mb:product_reference\s*\}\}/gi, 'SKU: DF-VERONA-320')
    .replace(/\{\{\s*mb:category_name\s*\}\}/gi, 'Living Room Sanctuary')
    .replace(/\{\{\s*mb:cart_count\s*\}\}/gi, '2')
    .replace(/\{\{\s*mb:page_title\s*\}\}/gi, 'Architectural Furniture Collection');
}
</script>

<template>
  <div class="mb-wysiwyg-widget" :style="containerStyle">
    <!-- 1. HERO SLIDER / CAROUSEL -->
    <div
      v-if="
        widget.type === 'creative_slider' ||
        widget.type === 'image_carousel' ||
        widget.type === 'testimonial_carousel'
      "
      style="position: relative; border-radius: 12px; overflow: hidden; min-height: 250px; background: #0f172a; color: #ffffff; display: flex; flex-direction: column; justify-content: flex-end"
    >
      <img
        :src="
          widget.content.items?.[0]?.src ||
          'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?auto=format&fit=crop&w=1200&q=80'
        "
        alt="Hero Slide"
        style="position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; opacity: 0.5"
      />
      <div style="position: relative; z-index: 2; padding: 24px; background: linear-gradient(to top, rgba(15,23,42,0.92), transparent)">
        <span
          style="display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 11px; font-weight: 800; margin-bottom: 8px; color: #ffffff"
          :style="{ backgroundColor: widget.style.accent_color || '#2563eb' }"
        >
          {{ widget.content.items?.[0]?.badge || 'FEATURED COLLECTION' }}
        </span>
        <h3 style="margin: 0 0 6px; font-size: 22px; font-weight: 800; color: #ffffff">
          {{ formatDynamicText(widget.content.items?.[0]?.title || widget.content.title, widget.label) }}
        </h3>
        <p style="margin: 0 0 14px; font-size: 13.5px; color: #e2e8f0">
          {{
            widget.content.items?.[0]?.subtitle ||
            widget.content.items?.[0]?.answer ||
            'Architectural design crafted in solid hardwood and Italian textiles.'
          }}
        </p>
        <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px">
          <span
            style="display: inline-flex; align-items: center; padding: 9px 18px; border-radius: 8px; font-weight: 700; font-size: 13px; color: #ffffff"
            :style="{ backgroundColor: widget.style.accent_color || '#2563eb' }"
          >
            {{ widget.content.items?.[0]?.cta_text || 'Explore Collection →' }}
          </span>
          <div style="display: flex; gap: 6px">
            <span
              v-for="(sl, idx) in (widget.content.items || [1, 2, 3]).slice(0, 4)"
              :key="idx"
              style="padding: 4px 9px; border-radius: 999px; font-size: 11px; font-weight: 700"
              :style="{
                background: idx === 0 ? '#ffffff' : 'rgba(255,255,255,0.22)',
                color: idx === 0 ? '#0f172a' : '#ffffff',
              }"
            >
              Slide {{ idx + 1 }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- 2. MEGAMENU NAVIGATION BAR (bar + the first item's panel, static) -->
    <div v-else-if="widget.type === 'nav_megamenu'" class="mb-cv-mm">
      <div v-if="menuItems.length === 0" class="mb-cv-mm-empty">
        {{ widget.content.source === 'categories' ? 'No store categories to show yet.' : 'Add menu items in the inspector.' }}
      </div>
      <template v-else>
        <div v-if="viewport === 'mobile'" class="mb-cv-mm-burger">☰ {{ widget.content.menu_label || 'Menu' }}</div>
        <ul class="mb-cv-mm-bar" :class="{ 'is-mobile': viewport === 'mobile' }">
          <li v-for="(item, idx) in menuItems" :key="idx" :class="{ 'is-first': idx === 0 }">
            <span>{{ item.title }}</span>
            <span v-if="item.badge" class="mb-cv-mm-badge">{{ item.badge }}</span>
            <span v-if="item.columns.length || item.promo_title" aria-hidden="true">▾</span>
          </li>
        </ul>
        <div v-if="menuItems[0].columns.length || menuItems[0].promo_title" class="mb-cv-mm-panel">
          <div v-for="(col, c) in menuItems[0].columns" :key="c">
            <strong v-if="col.heading">{{ col.heading }}</strong>
            <span v-for="(l, k) in col.links" :key="k">{{ l.title }}</span>
          </div>
          <div v-if="menuItems[0].promo_title" class="mb-cv-mm-promo">{{ menuItems[0].promo_title }}</div>
        </div>
        <div v-if="widget.content.source === 'categories'" class="mb-cv-mm-note">Live store categories</div>
      </template>
    </div>

    <!-- 3. DYNAMIC PRODUCT GRID / PRODUCT CAROUSEL -->
    <div
      v-else-if="
        widget.type === 'product_grid' || widget.type === 'product_carousel'
      "
    >
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px">
        <h3 style="margin: 0; font-size: 18px; font-weight: 800">
          {{ formatDynamicText(widget.content.title, 'Featured Catalog Showcase') }}
        </h3>
        <span style="font-size: 12px; font-weight: 700; color: #2563eb">
          View All ({{ activeCols }} Columns) →
        </span>
      </div>
      <div
        style="display: grid; gap: 12px"
        :style="{ gridTemplateColumns: `repeat(${activeCols}, minmax(0, 1fr))` }"
      >
        <div
          v-for="(prod, pIdx) in SAMPLE_PRODUCTS.slice(0, Math.max(2, activeCols))"
          :key="pIdx"
          style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; text-align: left"
        >
          <div style="position: relative; height: 130px; background: #f1f5f9">
            <img
              :src="prod.img"
              :alt="prod.title"
              style="width: 100%; height: 100%; object-fit: cover"
            />
            <span
              style="position: absolute; top: 8px; left: 8px; padding: 3px 8px; border-radius: 999px; font-size: 10px; font-weight: 800; color: #ffffff"
              :style="{ backgroundColor: widget.style.accent_color || '#2563eb' }"
            >
              {{ prod.badge }}
            </span>
          </div>
          <div style="padding: 10px 12px">
            <span style="font-size: 10.5px; color: #64748b; text-transform: uppercase; font-weight: 700">
              {{ prod.category }}
            </span>
            <div style="font-weight: 700; font-size: 13px; color: #0f172a; margin: 2px 0 6px">
              {{ prod.title }}
            </div>
            <div style="display: flex; align-items: center; justify-content: space-between">
              <strong style="font-size: 14px; color: #1d4ed8">{{ prod.price }}</strong>
              <span style="padding: 4px 10px; border-radius: 6px; background: #eff6ff; color: #1d4ed8; font-size: 11px; font-weight: 700">
                + Add to Cart
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 4. CATEGORY GRID / TRUST BADGES / ARTICLES GRID -->
    <div
      v-else-if="
        widget.type === 'category_grid' ||
        widget.type === 'trust_badges' ||
        widget.type === 'articles_grid' ||
        widget.type === 'account_nav_links'
      "
    >
      <h3 v-if="widget.content.title" style="margin: 0 0 12px; font-size: 17px; font-weight: 800">
        {{ formatDynamicText(widget.content.title) }}
      </h3>
      <div
        style="display: grid; gap: 10px"
        :style="{
          gridTemplateColumns:
            viewport === 'mobile'
              ? '1fr'
              : `repeat(${Math.min(4, (widget.content.items || []).length || 4)}, minmax(0, 1fr))`,
        }"
      >
        <div
          v-for="(item, idx) in (widget.content.items || []).slice(0, 4)"
          :key="idx"
          style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; padding: 12px; text-align: left"
        >
          <img
            v-if="item.src"
            :src="item.src"
            :alt="item.title"
            style="width: 100%; height: 100px; object-fit: cover; border-radius: 8px; margin-bottom: 8px"
          />
          <div style="font-weight: 800; font-size: 13px; color: #0f172a">
            {{ item.title }}
          </div>
          <div v-if="item.answer" style="font-size: 11.5px; color: #64748b; margin-top: 3px">
            {{ item.answer }}
          </div>
        </div>
      </div>
    </div>

    <!-- 5. SHOP THE LOOK IMAGE HOTSPOT -->
    <div v-else-if="widget.type === 'image_hotspot'">
      <h3 style="margin: 0 0 10px; font-size: 17px; font-weight: 800">
        {{ formatDynamicText(widget.content.title, 'Interactive Shop The Look') }}
      </h3>
      <div style="position: relative; border-radius: 12px; overflow: hidden; max-height: 260px">
        <img
          :src="
            widget.content.src ||
            'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?auto=format&fit=crop&w=1100&q=80'
          "
          alt="Shop The Look"
          style="width: 100%; height: 240px; object-fit: cover; display: block"
        />
        <div
          v-for="(pin, idx) in widget.content.items || []"
          :key="idx"
          style="position: absolute; transform: translate(-50%, -50%); display: flex; align-items: center; gap: 6px"
          :style="{ left: (pin.x || 40) + '%', top: (pin.y || 50) + '%' }"
        >
          <span
            style="width: 30px; height: 30px; border-radius: 999px; background: #2563eb; color: #ffffff; border: 2px solid #ffffff; display: inline-flex; align-items: center; justify-content: center; font-weight: 800; box-shadow: 0 4px 10px rgba(0,0,0,0.3)"
          >
            +
          </span>
          <span style="background: rgba(15,23,42,0.9); color: #ffffff; padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; white-space: nowrap">
            {{ pin.title }}
          </span>
        </div>
      </div>
    </div>

    <!-- 6. ACCORDION / TABS / TOGGLE -->
    <div
      v-else-if="
        widget.type === 'accordion' ||
        widget.type === 'tabs' ||
        widget.type === 'toggle'
      "
      style="display: flex; flex-direction: column; gap: 8px; text-align: left"
    >
      <div
        v-for="(item, idx) in (widget.content.items || [
          { title: widget.content.title || 'Specifications & Warranty', answer: widget.content.content || 'FSC-certified solid hardwood with white-glove home delivery.' },
        ]).slice(0, 4)"
        :key="idx"
        style="border: 1px solid #cbd5e1; border-radius: 8px; background: #ffffff; padding: 10px 14px"
      >
        <div style="display: flex; justify-content: space-between; align-items: center; font-weight: 700; font-size: 13.5px; color: #0f172a">
          <span>{{ item.title }}</span>
          <span style="color: #2563eb; font-weight: 800">{{ idx === 0 ? '−' : '+' }}</span>
        </div>
        <div v-if="idx === 0" style="margin-top: 6px; font-size: 12.5px; color: #475569">
          {{ item.answer || item.content }}
        </div>
      </div>
    </div>

    <!-- 7. IMAGE / PRODUCT GALLERY / FEATURE CARD / CTA -->
    <div
      v-else-if="
        widget.type === 'image' ||
        widget.type === 'product_images' ||
        widget.type === 'image_box' ||
        widget.type === 'product_box' ||
        widget.type === 'call_to_action' ||
        widget.type === 'flip_box'
      "
      style="display: flex; flex-wrap: wrap; gap: 14px; align-items: center; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px"
    >
      <img
        v-if="widget.content.src || widget.type === 'image' || widget.type === 'product_images'"
        :src="
          widget.content.src ||
          'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?auto=format&fit=crop&w=800&q=80'
        "
        :alt="widget.label"
        style="width: 180px; height: 125px; object-fit: cover; border-radius: 8px; flex-shrink: 0"
      />
      <div style="flex: 1 1 200px">
        <span
          v-if="widget.content.badge"
          style="display: inline-block; padding: 3px 9px; border-radius: 999px; font-size: 10px; font-weight: 800; color: #ffffff; margin-bottom: 6px"
          :style="{ backgroundColor: widget.style.accent_color || '#2563eb' }"
        >
          {{ widget.content.badge }}
        </span>
        <h4 style="margin: 0 0 6px; font-size: 16px; font-weight: 800">
          {{
            formatDynamicText(
              widget.content.title ||
                widget.content.front_title ||
                widget.content.caption ||
                widget.label
            )
          }}
        </h4>
        <p
          v-if="widget.content.text || widget.content.front_desc || widget.content.price"
          style="margin: 0 0 10px; font-size: 13px; color: #475569"
        >
          {{ widget.content.text || widget.content.front_desc || widget.content.price }}
        </p>
        <span
          v-if="widget.content.cta_text"
          style="display: inline-flex; padding: 7px 14px; border-radius: 8px; color: #ffffff; font-size: 12.5px; font-weight: 700"
          :style="{ backgroundColor: widget.style.accent_color || '#2563eb' }"
        >
          {{ widget.content.cta_text }}
        </span>
      </div>
    </div>

    <!-- 8. SEARCH BAR / NEWSLETTER / CONTACT FORM -->
    <div
      v-else-if="
        widget.type === 'ajax_search' ||
        widget.type === 'email_subscription' ||
        widget.type === 'contact_form' ||
        widget.type === 'customer_login' ||
        widget.type === 'customer_register'
      "
      style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px"
    >
      <div v-if="widget.content.title" style="font-weight: 800; font-size: 15px; margin-bottom: 4px">
        {{ formatDynamicText(widget.content.title) }}
      </div>
      <div v-if="widget.content.subtitle" style="font-size: 12.5px; color: #64748b; margin-bottom: 10px">
        {{ widget.content.subtitle }}
      </div>
      <div style="display: flex; gap: 8px; flex-wrap: wrap">
        <div
          style="flex: 1 1 180px; padding: 9px 12px; border-radius: 8px; background: #ffffff; border: 1px solid #cbd5e1; color: #64748b; font-size: 13px"
        >
          🔍 {{ widget.content.placeholder || 'Type here...' }}
        </div>
        <span
          style="padding: 9px 18px; border-radius: 8px; color: #ffffff; font-weight: 700; font-size: 13px"
          :style="{ backgroundColor: widget.style.accent_color || '#2563eb' }"
        >
          {{ widget.content.button_text || 'Search / Submit' }}
        </span>
      </div>
    </div>

    <!-- 9. BUTTON / ADD TO CART -->
    <div
      v-else-if="
        widget.type === 'button' ||
        widget.type === 'add_to_cart' ||
        widget.type === 'lightbox_trigger'
      "
      style="display: flex; flex-wrap: wrap; gap: 10px; align-items: center"
      :style="{
        justifyContent:
          widget.style.text_align?.[viewport] === 'center'
            ? 'center'
            : widget.style.text_align?.[viewport] === 'right'
            ? 'flex-end'
            : 'flex-start',
      }"
    >
      <span
        style="display: inline-flex; align-items: center; gap: 8px; padding: 11px 22px; border-radius: 8px; color: #ffffff; font-weight: 700; font-size: 14px; box-shadow: 0 4px 12px rgba(37,99,235,0.22)"
        :style="{ backgroundColor: widget.style.accent_color || '#2563eb' }"
      >
        🛒 {{ formatDynamicText(widget.content.text || widget.content.button_text, widget.label) }}
      </span>
      <span
        v-if="widget.content.secondary_text"
        style="display: inline-flex; align-items: center; padding: 10px 16px; border-radius: 8px; border: 1px solid #cbd5e1; background: #ffffff; color: #0f172a; font-weight: 600; font-size: 13px"
      >
        {{ widget.content.secondary_text }}
      </span>
    </div>

    <!-- DIVIDER -->
    <hr
      v-else-if="widget.type === 'divider'"
      class="mb-divider"
      :style="{
        border: 0,
        borderTop: `${Math.min(20, Math.max(1, Number(widget.content.thickness) || 1))}px ${
          ['solid', 'dashed', 'dotted', 'double'].includes(widget.content.style_type) ? widget.content.style_type : 'solid'
        } ${widget.style.accent_color || 'currentColor'}`,
        margin: 0,
      }"
    />

    <!-- SPACER -->
    <div
      v-else-if="widget.type === 'spacer'"
      class="mb-spacer"
      aria-hidden="true"
      :style="{
        height: `${Math.min(400, Math.max(0, Number(widget.content.height_px ?? 32)))}px`,
        background: 'repeating-linear-gradient(45deg, transparent 0 6px, rgba(148,163,184,0.15) 6px 12px)',
      }"
    ></div>

    <!-- 10. HEADING / LOGO / PRICE / RATING / GENERIC -->
    <div v-else>
      <span
        v-if="widget.content.subtitle || widget.content.brand || widget.content.badge"
        style="display: inline-block; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.07em; margin-bottom: 4px"
        :style="{ color: widget.style.accent_color || '#2563eb' }"
      >
        {{ widget.content.subtitle || widget.content.brand || widget.content.badge }}
      </span>
      <div style="font-weight: 800; font-size: 1.15em">
        {{
          formatDynamicText(
            widget.content.text ||
              widget.content.title ||
              widget.content.price ||
              widget.content.status ||
              widget.label
          )
        }}
      </div>
      <div
        v-if="widget.content.html"
        style="margin-top: 6px; font-size: 13.5px; color: #334155"
        v-html="formatDynamicText(widget.content.html)"
      ></div>
      <div
        v-if="widget.content.tagline || widget.content.swatches || widget.content.trail"
        style="margin-top: 4px; font-size: 12px; color: #64748b"
      >
        {{ formatDynamicText(widget.content.tagline || widget.content.swatches || widget.content.trail) }}
      </div>
    </div>
  </div>
</template>

<style scoped>
.mb-cv-mm {
  display: grid;
  gap: 8px;
  padding: 10px;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  background: #f8fafc;
  font-size: 13px;
}
.mb-cv-mm-empty,
.mb-cv-mm-note {
  color: #64748b;
  font-size: 11.5px;
}
.mb-cv-mm-burger {
  justify-self: start;
  padding: 6px 10px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: #fff;
  font-weight: 800;
}
.mb-cv-mm-bar {
  display: flex;
  flex-wrap: wrap;
  gap: 4px 14px;
  margin: 0;
  padding: 0;
  list-style: none;
  font-weight: 800;
  color: #0f172a;
}
.mb-cv-mm-bar.is-mobile {
  flex-direction: column;
}
.mb-cv-mm-bar li {
  display: flex;
  gap: 5px;
  align-items: center;
}
.mb-cv-mm-bar li.is-first {
  color: #2563eb;
}
.mb-cv-mm-badge {
  padding: 1px 5px;
  border-radius: 999px;
  background: #d97706;
  color: #fff;
  font-size: 9.5px;
}
.mb-cv-mm-panel {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(110px, 1fr));
  gap: 10px;
  padding: 10px;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  background: #fff;
}
.mb-cv-mm-panel > div {
  display: grid;
  gap: 3px;
  color: #475569;
  font-size: 12px;
}
.mb-cv-mm-panel strong {
  color: #0f172a;
}
.mb-cv-mm-promo {
  padding: 8px;
  border-radius: 6px;
  background: #f1f5f9;
  font-weight: 800;
  color: #0f172a;
}
</style>
