<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import type { BootPayload, WidgetNode } from './types';
import { createStudioStore } from './useStudioStore';
import CanvasWidgetPreview from './CanvasWidgetPreview.vue';
import MegaMenuEditor from './MegaMenuEditor.vue';

const props = defineProps<{ boot: BootPayload }>();
const store = createStudioStore(props.boot);
const {
  state,
  filteredDashboardTemplates,
  subStudioTemplates,
  allSubComponents,
  comingSoonTypes,
  filteredWidgets,
  selectedSection,
  selectedWidget,
} = store;

// Drag-and-drop visual placement guide state
const dragOverColId = ref<string>('');
const draggedWidgetLabel = ref<string>('');

const SECTION_ICONS: Record<string, string> = {
  all: '🌐',
  header: '🧭',
  homepage: '🏠',
  product: '🛋️',
  category: '📂',
  footer: '🦶',
  cms: '📄',
  account: '👤',
  prices_drop: '🏷️',
  new_products: '✨',
  best_sales: '🏆',
  search: '🔍',
  checkout: '🛒',
  manufacturers: '🏛️',
  page_404: '🛟',
  custom: '⚡',
};

const WIDGET_EMOJIS: Record<string, string> = {
  basic: '🧱',
  general: '✨',
  premium: '🚀',
  site: '🧭',
  catalog: '🛍️',
  product: '🛋️',
  listing_customer: '👤',
};

const CATEGORY_LABELS: Record<string, string> = {
  all: '📚 All Visual Building Blocks (77)',
  basic: '🧱 1. Layout, Headings, Photos & Buttons (11)',
  general: '✨ 2. Accordions, Tabs, Lists & Reviews (15)',
  premium: '🚀 3. Hero Sliders, Hotspots, Deals & Forms (14)',
  site: '🧭 4. Header: Logo, MegaMenu, Search & Cart (10)',
  catalog: '🛍️ 5. Catalog: Product Grids & Carousels (6)',
  product: '🛋️ 6. Product Page: Price, Gallery & Buy Box (10)',
  listing_customer: '👤 7. Category Filters & Customer Account (11)',
};

const DYNAMIC_TAGS = [
  { tag: '{{mb:shop_name}}', label: '🏪 Store Name' },
  { tag: '{{mb:product_name}}', label: '🛋️ Product Title' },
  { tag: '{{mb:product_price}}', label: '💲 Product Price' },
  { tag: '{{mb:product_reference}}', label: '🏷️ Product SKU / Reference' },
  { tag: '{{mb:category_name}}', label: '📂 Category Name' },
  { tag: '{{mb:cart_count}}', label: '🛒 Shopping Cart Item Count' },
  { tag: '{{mb:page_title}}', label: '📄 Current Page Title' },
];

const CURATED_SWATCHES = [
  { color: '#ffffff', label: 'Pure White' },
  { color: '#f8fafc', label: 'Soft Ivory' },
  { color: '#f1f5f9', label: 'Pearl Grey' },
  { color: '#0f172a', label: 'Obsidian Slate' },
  { color: '#2563eb', label: 'Royal Blue' },
  { color: '#d97706', label: 'Golden Oak' },
  { color: '#15803d', label: 'Sage Emerald' },
  { color: '#b91c1c', label: 'Terracotta Red' },
  { color: '#7c3aed', label: 'Royal Velvet' },
];

const VISUAL_CSS_PRESETS: Record<string, { label: string; css: string }> = {
  none: { label: 'No extra effect (Clean Standard)', css: '' },
  hover_lift: {
    label: '✨ Gentle Hover Lift & Elevation',
    css: 'transition: transform 0.2s ease, box-shadow 0.2s ease;',
  },
  glass_card: {
    label: '💎 Frosted Glass Card (Glassmorphism)',
    css: 'backdrop-filter: blur(10px); background-color: rgba(255, 255, 255, 0.85);',
  },
  soft_gradient: {
    label: '🌅 Soft Editorial Gradient Backdrop',
    css: 'background-image: linear-gradient(135deg, #f8fafc 0%, #eff6ff 100%);',
  },
  gold_accent_left: {
    label: '🏛️ Brand Accent Left Highlight Bar (4px)',
    css: 'border-left: 4px solid #2563eb;',
  },
};

function applyBlockStyleSkin(
  w: WidgetNode,
  skin: 'clean' | 'card_shadow' | 'dark_luxury' | 'brand_tint' | 'gold_frame'
) {
  store.pushUndoSnapshot();
  if (skin === 'clean') {
    w.style.bg_color = 'transparent';
    w.style.text_color = '#0f172a';
    w.style.accent_color = '#2563eb';
    w.style.border_width = 0;
    w.style.shadow = 'none';
  } else if (skin === 'card_shadow') {
    w.style.bg_color = '#ffffff';
    w.style.text_color = '#0f172a';
    w.style.accent_color = '#2563eb';
    w.style.border_width = 1;
    w.style.border_color = '#e2e8f0';
    w.style.border_radius = 14;
    w.style.shadow = 'md';
  } else if (skin === 'dark_luxury') {
    w.style.bg_color = '#0f172a';
    w.style.text_color = '#f8fafc';
    w.style.accent_color = '#d97706';
    w.style.border_width = 1;
    w.style.border_color = '#334155';
    w.style.border_radius = 14;
    w.style.shadow = 'lg';
  } else if (skin === 'brand_tint') {
    w.style.bg_color = '#eff6ff';
    w.style.text_color = '#1e3a8a';
    w.style.accent_color = '#2563eb';
    w.style.border_width = 1;
    w.style.border_color = '#bfdbfe';
    w.style.border_radius = 12;
    w.style.shadow = 'sm';
  } else if (skin === 'gold_frame') {
    w.style.bg_color = '#fffbeb';
    w.style.text_color = '#0f172a';
    w.style.accent_color = '#d97706';
    w.style.border_width = 2;
    w.style.border_color = '#d97706';
    w.style.border_radius = 12;
    w.style.shadow = 'sm';
  }
}

function applySpacingPreset(
  w: WidgetNode,
  preset: 'compact' | 'normal' | 'spacious' | 'xl'
) {
  store.pushUndoSnapshot();
  const vp = state.viewport;
  if (preset === 'compact') {
    w.advanced.padding_y[vp] = 6;
    w.advanced.padding_x[vp] = 8;
  } else if (preset === 'normal') {
    w.advanced.padding_y[vp] = 14;
    w.advanced.padding_x[vp] = 16;
  } else if (preset === 'spacious') {
    w.advanced.padding_y[vp] = 28;
    w.advanced.padding_x[vp] = 24;
  } else if (preset === 'xl') {
    w.advanced.padding_y[vp] = 44;
    w.advanced.padding_x[vp] = 32;
  }
}

function toHexColor(val: string | undefined, fallback = '#0f172a'): string {
  if (!val || val === 'transparent') return fallback;
  if (/^#[0-9a-fA-F]{6}$/.test(val)) return val;
  return fallback;
}

function onKeyDown(e: KeyboardEvent) {
  if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'z' && !e.shiftKey) {
    if (state.activeNav === 'editor') {
      e.preventDefault();
      store.undo();
    }
  } else if (
    (e.ctrlKey || e.metaKey) &&
    (e.key.toLowerCase() === 'y' || (e.shiftKey && e.key.toLowerCase() === 'z'))
  ) {
    if (state.activeNav === 'editor') {
      e.preventDefault();
      store.redo();
    }
  } else if (
    (e.ctrlKey || e.metaKey) &&
    (e.key.toLowerCase() === 'k' || e.key.toLowerCase() === 'i')
  ) {
    e.preventDefault();
    state.showFinderModal = !state.showFinderModal;
  } else if ((e.ctrlKey || e.metaKey) && e.key === '?') {
    e.preventDefault();
    state.showShortcutsModal = !state.showShortcutsModal;
  } else if (e.key === 'Escape') {
    state.showNewTemplateModal = false;
    state.showLivePreviewModal = false;
    state.showFinderModal = false;
    state.showShortcutsModal = false;
    state.showDesignMdModal = false;
    state.showMediaPickerModal = false;
  }
}

function onBeforeUnload(e: BeforeUnloadEvent) {
  if (state.isDirty) {
    e.preventDefault();
    e.returnValue = '';
  }
}

onMounted(() => {
  window.addEventListener('keydown', onKeyDown);
  window.addEventListener('beforeunload', onBeforeUnload);
});

onUnmounted(() => {
  window.removeEventListener('keydown', onKeyDown);
  window.removeEventListener('beforeunload', onBeforeUnload);
});

function handleDragStart(widgetType: string, label: string) {
  state.draggedWidgetType = widgetType;
  draggedWidgetLabel.value = label;
}

function handleDragEnd() {
  dragOverColId.value = '';
  draggedWidgetLabel.value = '';
}

function handleDragOverCol(colId: string) {
  dragOverColId.value = colId;
}

function handleDropOnColumn(colId: string) {
  dragOverColId.value = '';
  if (!state.draggedWidgetType) return;
  store.insertWidget(state.draggedWidgetType, colId);
  state.draggedWidgetType = '';
  draggedWidgetLabel.value = '';
}

function insertDynamicTag(tag: string) {
  const w = selectedWidget.value;
  if (!w) return;
  store.pushUndoSnapshot();
  if (typeof w.content.text === 'string') {
    w.content.text += ' ' + tag;
  } else if (typeof w.content.title === 'string') {
    w.content.title += ' ' + tag;
  } else {
    w.content.text = tag;
  }
}

function addRepeaterItem() {
  const w = selectedWidget.value;
  if (!w) return;
  store.pushUndoSnapshot();
  if (!Array.isArray(w.content.items)) {
    w.content.items = [];
  }
  w.content.items.push({
    title: `New Item #${w.content.items.length + 1}`,
    answer: 'Customizable specification or slide description.',
    badge: 'FEATURED',
    cta_text: 'Explore',
  });
}

function removeRepeaterItem(idx: number) {
  const w = selectedWidget.value;
  if (!w || !Array.isArray(w.content.items)) return;
  store.pushUndoSnapshot();
  w.content.items.splice(idx, 1);
}

async function handleFileUploadInput(ev: Event) {
  const input = ev.target as HTMLInputElement;
  const file = input.files?.[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = async () => {
    const result = typeof reader.result === 'string' ? reader.result : '';
    await store.uploadMediaContent(file.name, result);
    input.value = '';
  };
  if (file.name.toLowerCase().endsWith('.svg')) {
    reader.readAsText(file);
  } else {
    reader.readAsDataURL(file);
  }
}

function applyThemePreset(
  preset: 'obsidian_pro' | 'nordic_light' | 'emerald_craft' | 'royal_velvet'
) {
  state.studioTheme.preset = preset;
  if (preset === 'obsidian_pro') {
    state.studioTheme.mode = 'dark';
    state.studioTheme.accent_color = '#3b82f6';
  } else if (preset === 'nordic_light') {
    state.studioTheme.mode = 'light';
    state.studioTheme.accent_color = '#1d4ed8';
  } else if (preset === 'emerald_craft') {
    state.studioTheme.mode = 'light';
    state.studioTheme.accent_color = '#15803d';
  } else if (preset === 'royal_velvet') {
    state.studioTheme.mode = 'dark';
    state.studioTheme.accent_color = '#7c3aed';
  }
}

function countTemplatesForSection(secKey: string): number {
  if (secKey === 'all') {
    return state.boot.templates.filter((t) => t.entity_kind === 'page_template')
      .length;
  }
  return state.boot.templates.filter(
    (t) => t.entity_kind === 'page_template' && t.section_type === secKey
  ).length;
}
</script>

<template>
  <div
    class="mb-studio-root"
    :data-theme="state.studioTheme.mode"
    :data-density="state.studioTheme.density"
    :style="{
      '--mb-ui-radius': state.studioTheme.radius_px + 'px',
    }"
  >
    <!-- ===================================================================
         GLOBAL WORKSPACE TOP NAVIGATION BAR (FRIENDLY & NON-TECHNICAL)
         =================================================================== -->
    <header class="mb-top-header">
      <div class="mb-brand-cluster">
        <span class="mb-brand-badge">MB</span>
        <div>
          <h1 class="mb-brand-title">{{ state.studioTheme.brand_title }}</h1>
          <span class="mb-brand-sub">
            Visual Drag & Drop Page Studio • {{ state.boot.shopName }} (PrestaShop {{ state.boot.psVersion }})
          </span>
        </div>
      </div>

      <nav class="mb-nav-tabs" aria-label="Mega Builder Main Navigation">
        <button
          type="button"
          class="mb-nav-btn"
          :class="{ 'is-active': state.activeNav === 'dashboard' }"
          @click="state.activeNav = 'dashboard'"
        >
          🏠 1. My Store Pages (15)
        </button>
        <button
          type="button"
          class="mb-nav-btn"
          :class="{ 'is-active': state.activeNav === 'editor' }"
          @click="state.activeNav = 'editor'"
        >
          🎨 2. Live Visual Canvas
        </button>
        <button
          type="button"
          class="mb-nav-btn"
          :class="{ 'is-active': state.activeNav === 'sub_studios' }"
          @click="state.activeNav = 'sub_studios'"
        >
          🧩 3. Menus, Sliders & Cards (4)
        </button>
        <button
          type="button"
          class="mb-nav-btn"
          :class="{ 'is-active': state.activeNav === 'global_settings' }"
          @click="state.activeNav = 'global_settings'"
        >
          🎨 Global Colors & Fonts
        </button>
        <button
          type="button"
          class="mb-nav-btn"
          :class="{ 'is-active': state.activeNav === 'media_manager' }"
          @click="state.activeNav = 'media_manager'"
        >
          🖼️ Photos & Icons
        </button>
        <button
          type="button"
          class="mb-nav-btn"
          :class="{ 'is-active': state.activeNav === 'studio_theme' }"
          @click="state.activeNav = 'studio_theme'"
        >
          ⚙️ Studio Theme
        </button>
      </nav>

      <div class="mb-header-utilities">
        <button
          type="button"
          class="mb-btn-ui"
          title="Quick Page & Block Finder (Ctrl+K)"
          @click="state.showFinderModal = true"
        >
          🔍 Quick Finder
        </button>
        <button
          type="button"
          class="mb-btn-ui"
          title="Switch Light / Dark Studio Theme"
          @click="
            state.studioTheme.mode =
              state.studioTheme.mode === 'light' ? 'dark' : 'light'
          "
        >
          {{ state.studioTheme.mode === 'light' ? '🌙 Dark Mode' : '☀️ Light Mode' }}
        </button>
        <button
          type="button"
          class="mb-btn-ui is-primary"
          @click="state.showNewTemplateModal = true"
        >
          ✨ + Create New Page
        </button>
      </div>
    </header>

    <!-- TOAST FEEDBACK BANNER -->
    <div
      v-if="state.toastMessage"
      class="mb-toast-bar"
      :class="{ 'is-error': state.toastType === 'error' }"
      role="status"
    >
      <span>✅ {{ state.toastMessage }}</span>
      <button
        type="button"
        class="mb-btn-ui"
        style="min-height: 34px; padding: 4px 12px"
        @click="state.toastMessage = ''"
      >
        Dismiss
      </button>
    </div>

    <!-- ===================================================================
         VIEW 1: TEMPLATES DASHBOARD (ALL 15 PAGE SECTIONS WITH VISUAL CARDS)
         =================================================================== -->
    <main v-if="state.activeNav === 'dashboard'" class="mb-workspace-body">
      <!-- Friendly 3-Step Visual Guide for Non-Technical Users -->
      <div class="mb-guided-stepper">
        <div class="mb-step-item">
          <span class="mb-step-num">1</span>
          <div class="mb-step-text">
            <strong>Pick which part of your store to customize</strong>
            <span>Choose Header, Homepage, Product Page, Category, or Footer below.</span>
          </div>
        </div>
        <div class="mb-step-item">
          <span class="mb-step-num">2</span>
          <div class="mb-step-text">
            <strong>Drag & drop visual blocks — Zero code needed</strong>
            <span>Use 1-click style presets, visual color pickers, and guided drop zones.</span>
          </div>
        </div>
        <div class="mb-step-item">
          <span class="mb-step-num">3</span>
          <div class="mb-step-text">
            <strong>Preview on Mobile & click Publish Live</strong>
            <span>All designs stay safely in Draft mode until you are ready to publish.</span>
          </div>
        </div>
      </div>

      <!-- 15 Visual Page Section Filter Cards -->
      <div
        class="mb-section-filters-grid"
        role="tablist"
        aria-label="15 Storefront Page Sections"
      >
        <button
          type="button"
          class="mb-filter-card"
          :class="{ 'is-active': state.activeSectionFilter === 'all' }"
          @click="state.activeSectionFilter = 'all'"
        >
          <span class="mb-filter-icon">🌐</span>
          <div>
            <span class="mb-filter-card-title">All Store Pages</span>
            <span class="mb-filter-card-count">
              {{ countTemplatesForSection('all') }} ready templates
            </span>
          </div>
        </button>
        <button
          v-for="(meta, secKey) in state.boot.pageSections"
          :key="secKey"
          type="button"
          class="mb-filter-card"
          :class="{ 'is-active': state.activeSectionFilter === String(secKey) }"
          @click="state.activeSectionFilter = String(secKey)"
        >
          <span class="mb-filter-icon">{{ SECTION_ICONS[String(secKey)] || '📄' }}</span>
          <div>
            <span class="mb-filter-card-title">{{ meta.label }}</span>
            <span class="mb-filter-card-count">
              {{ countTemplatesForSection(String(secKey)) }} templates
            </span>
          </div>
        </button>
      </div>

      <!-- Search & Action Bar -->
      <div class="mb-dashboard-toolbar">
        <div style="display: flex; flex-wrap: wrap; gap: 10px; flex: 1 1 320px">
          <input
            v-model="state.dashboardSearch"
            type="search"
            class="mb-input-ui"
            style="max-width: 300px"
            placeholder="🔍 Search templates by name..."
            aria-label="Search templates"
          />
          <select
            v-model="state.statusFilter"
            class="mb-select-ui"
            style="max-width: 190px"
            aria-label="Filter by status"
          >
            <option value="all">All Statuses</option>
            <option value="active">🟢 Published Live</option>
            <option value="draft">📝 Safe Drafts</option>
          </select>
        </div>
        <div style="display: flex; flex-wrap: wrap; gap: 8px">
          <button
            v-if="state.activeSectionFilter !== 'all'"
            type="button"
            class="mb-btn-ui is-primary"
            @click="
              store.startFromBlueprint(
                state.activeSectionFilter,
                'page_template'
              )
            "
          >
            ✨ Open Pre-Built
            {{ state.boot.pageSections[state.activeSectionFilter]?.label }} Starter Blueprint
          </button>
          <button
            type="button"
            class="mb-btn-ui"
            @click="store.exportAllTemplatesJson()"
          >
            ⬇️ Export JSON Backup
          </button>
          <button
            type="button"
            class="mb-btn-ui is-primary"
            @click="state.showNewTemplateModal = true"
          >
            + Create New Template
          </button>
        </div>
      </div>

      <!-- Visual Template Cards Grid -->
      <div class="mb-templates-grid">
        <article
          v-for="tpl in filteredDashboardTemplates"
          :key="tpl.id_template"
          class="mb-tpl-card"
        >
          <div>
            <!-- Graphical Mini Preview Banner -->
            <div class="mb-tpl-mini-preview">
              <div style="display: flex; justify-content: space-between; align-items: center">
                <span style="font-size: 24px">
                  {{ SECTION_ICONS[tpl.section_type] || '🎨' }}
                </span>
                <span
                  class="mb-status-pill"
                  :class="Number(tpl.active) === 1 ? 'is-live' : 'is-draft'"
                >
                  {{ Number(tpl.active) === 1 ? '🟢 Live on Store' : '📝 Draft' }}
                </span>
              </div>
              <div>
                <div style="font-size: 11px; color: #93c5fd; font-weight: 700; text-transform: uppercase">
                  {{ state.boot.pageSections[tpl.section_type]?.label || tpl.section_type }}
                </div>
                <div style="font-size: 15px; font-weight: 800; color: #ffffff">
                  {{ tpl.title }}
                </div>
              </div>
            </div>

            <div style="display: flex; justify-content: space-between; margin-top: 10px; font-size: 12px; color: var(--mb-ui-muted)">
              <span>📦 {{ tpl.sections_count || 1 }} Visual Sections</span>
              <span>🕒 {{ tpl.revision_count || 1 }} Saved Snapshots</span>
            </div>
          </div>

          <div class="mb-tpl-actions">
            <button
              type="button"
              class="mb-btn-ui is-primary"
              style="flex: 1"
              @click="store.openTemplateInEditor(tpl.id_template)"
            >
              🎨 Open in Visual Editor
            </button>
            <button
              type="button"
              class="mb-btn-ui"
              :class="{ 'is-success': Number(tpl.active) === 0 }"
              @click="store.toggleTemplateStatus(tpl)"
            >
              {{ Number(tpl.active) === 1 ? 'Set to Draft' : '🚀 Publish' }}
            </button>
            <button
              type="button"
              class="mb-btn-ui"
              title="Duplicate template"
              @click="store.duplicateTemplate(tpl.id_template)"
            >
              ⧉ Clone
            </button>
            <button
              type="button"
              class="mb-btn-ui is-danger"
              title="Delete template"
              @click="store.deleteTemplate(tpl.id_template)"
            >
              🗑️
            </button>
          </div>
        </article>
      </div>
    </main>

    <!-- ===================================================================
         VIEW 2: 4 COMPONENT SUB-STUDIOS (MEGAMENU, SLIDERS, CARDS, ACCORDION)
         =================================================================== -->
    <main v-else-if="state.activeNav === 'sub_studios'" class="mb-workspace-body">
      <div class="mb-guided-stepper">
        <div class="mb-step-item">
          <span class="mb-step-num">🧩</span>
          <div class="mb-step-text">
            <strong>Reusable Components (MegaMenus, Sliders, Cards & Accordions)</strong>
            <span>Design a component once here and embed it into any page across your store in 1 click.</span>
          </div>
        </div>
      </div>

      <div class="mb-dashboard-toolbar">
        <div style="display: flex; flex-wrap: wrap; gap: 8px">
          <button
            v-for="(meta, subKey) in state.boot.subComponents"
            :key="subKey"
            type="button"
            class="mb-nav-btn"
            :class="{ 'is-active': state.activeSubStudioKind === subKey }"
            @click="state.activeSubStudioKind = subKey as any"
          >
            🧩 {{ meta.label }}
          </button>
        </div>
        <button
          type="button"
          class="mb-btn-ui is-primary"
          @click="
            store.startFromBlueprint(state.activeSubStudioKind, 'sub_component')
          "
        >
          ✨ + Create New {{ state.boot.subComponents[state.activeSubStudioKind]?.label }}
        </button>
      </div>

      <div class="mb-templates-grid">
        <article
          v-for="sub in subStudioTemplates"
          :key="sub.id_template"
          class="mb-tpl-card"
        >
          <div class="mb-tpl-mini-preview">
            <span style="font-size: 24px">🧩</span>
            <div>
              <div style="font-size: 11px; color: #93c5fd; font-weight: 700">
                REUSABLE COMPONENT #{{ sub.id_template }}
              </div>
              <div style="font-size: 15px; font-weight: 800">{{ sub.title }}</div>
            </div>
          </div>
          <div class="mb-tpl-actions">
            <button
              type="button"
              class="mb-btn-ui is-primary"
              style="flex: 1"
              @click="store.openTemplateInEditor(sub.id_template)"
            >
              🎨 Edit Component Visually
            </button>
            <button
              type="button"
              class="mb-btn-ui"
              @click="store.duplicateTemplate(sub.id_template)"
            >
              ⧉ Clone
            </button>
            <button
              type="button"
              class="mb-btn-ui is-danger"
              @click="store.deleteTemplate(sub.id_template)"
            >
              🗑️
            </button>
          </div>
        </article>
      </div>
    </main>

    <!-- ===================================================================
         VIEW 3: 3-PANE VISUAL STUDIO CANVAS EDITOR (WYSIWYG + VISUAL GUIDES)
         =================================================================== -->
    <main v-else-if="state.activeNav === 'editor'" class="mb-workspace-body">
      <!-- Editor Top Toolbar -->
      <div class="mb-editor-toolbar">
        <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 8px">
          <button
            type="button"
            class="mb-btn-ui"
            @click="state.activeNav = 'dashboard'"
          >
            ← Back to Pages
          </button>
          <input
            v-model="state.currentTemplate.title"
            type="text"
            class="mb-input-ui"
            style="max-width: 260px; font-weight: 700"
            aria-label="Template Title"
            @input="state.isDirty = true"
          />
          <select
            v-model="state.currentTemplate.section_type"
            class="mb-select-ui"
            style="max-width: 190px"
            aria-label="Storefront Section"
          >
            <optgroup label="15 Storefront Page Sections">
              <option
                v-for="(m, k) in state.boot.pageSections"
                :key="k"
                :value="k"
              >
                {{ SECTION_ICONS[String(k)] || '📄' }} {{ m.label }}
              </option>
            </optgroup>
            <optgroup label="4 Reusable Sub-Components">
              <option
                v-for="(m, k) in state.boot.subComponents"
                :key="k"
                :value="k"
              >
                🧩 {{ m.label }}
              </option>
            </optgroup>
          </select>
        </div>

        <!-- 3-Viewport Responsive Mode Switcher -->
        <div
          style="display: flex; flex-wrap: wrap; gap: 6px"
          role="group"
          aria-label="Device Responsive Mode Switcher"
        >
          <button
            type="button"
            class="mb-nav-btn"
            :class="{ 'is-active': state.viewport === 'desktop' }"
            @click="state.viewport = 'desktop'"
          >
            🖥️ Desktop (1440px)
          </button>
          <button
            type="button"
            class="mb-nav-btn"
            :class="{ 'is-active': state.viewport === 'tablet' }"
            @click="state.viewport = 'tablet'"
          >
            📱 Tablet (768px)
          </button>
          <button
            type="button"
            class="mb-nav-btn"
            :class="{ 'is-active': state.viewport === 'mobile' }"
            @click="state.viewport = 'mobile'"
          >
            📲 Mobile (390px)
          </button>
        </div>

        <!-- Undo / Redo / History / Preview / Save / Publish -->
        <div style="display: flex; flex-wrap: wrap; gap: 6px; align-items: center">
          <button
            type="button"
            class="mb-btn-ui"
            :disabled="state.undoStack.length === 0"
            title="Undo last action (Ctrl+Z)"
            @click="store.undo()"
          >
            ↶ Undo
          </button>
          <button
            type="button"
            class="mb-btn-ui"
            :disabled="state.redoStack.length === 0"
            title="Redo action (Ctrl+Y)"
            @click="store.redo()"
          >
            ↷ Redo
          </button>
          <select
            v-if="(state.currentTemplate.revisions?.length || 0) > 0"
            class="mb-select-ui"
            style="max-width: 170px"
            aria-label="Restore revision snapshot"
            @change="
              (e) => {
                const val = Number((e.target as HTMLSelectElement).value);
                if (val > 0) store.restoreRevision(val);
              }
            "
          >
            <option value="0">
              🕒 History ({{ state.currentTemplate.revisions?.length }})
            </option>
            <option
              v-for="rev in state.currentTemplate.revisions"
              :key="rev.id_revision"
              :value="rev.id_revision"
            >
              #{{ rev.id_revision }} — {{ rev.label }}
            </option>
          </select>
          <button
            type="button"
            class="mb-btn-ui"
            @click="store.openLivePreview()"
          >
            👁️ Live Preview
          </button>
          <button
            type="button"
            class="mb-btn-ui is-primary"
            :disabled="state.isSaving"
            @click="store.saveCurrentTemplate(0)"
          >
            💾 Save Draft
          </button>
          <button
            type="button"
            class="mb-btn-ui is-success"
            :disabled="state.isSaving"
            @click="store.saveCurrentTemplate(1)"
          >
            🚀 Publish Live
          </button>
        </div>
      </div>

      <!-- Tablet / Mobile Slide-Over Drawer Toggles (< 1180px) -->
      <div class="mb-drawer-toggle-row">
        <button
          type="button"
          class="mb-btn-ui"
          @click="state.leftDrawerOpen = !state.leftDrawerOpen"
        >
          {{
            state.leftDrawerOpen
              ? '✕ Hide Block Library'
              : '➕ Open 77 Visual Blocks Library'
          }}
        </button>
        <button
          type="button"
          class="mb-btn-ui"
          @click="state.rightDrawerOpen = !state.rightDrawerOpen"
        >
          {{
            state.rightDrawerOpen
              ? '✕ Hide Design Inspector'
              : '🎨 Open Colors, Text & Style Panel'
          }}
        </button>
      </div>

      <div class="mb-editor-workbench">
        <!-- ===============================================================
             LEFT PANE: VISUAL 77-WIDGET LIBRARY, NAVIGATOR & SAVED BLOCKS
             =============================================================== -->
        <aside
          class="mb-editor-left-pane"
          :class="{ 'is-mobile-open': state.leftDrawerOpen }"
          aria-label="Visual Block Library"
        >
          <div style="display: flex; gap: 4px; margin-bottom: 10px">
            <button
              type="button"
              class="mb-nav-btn"
              style="flex: 1; padding: 6px 8px; font-size: 12px"
              :class="{ 'is-active': state.leftPanelTab === 'widgets' }"
              @click="state.leftPanelTab = 'widgets'"
            >
              ➕ Blocks (77)
            </button>
            <button
              type="button"
              class="mb-nav-btn"
              style="flex: 1; padding: 6px 8px; font-size: 12px"
              :class="{ 'is-active': state.leftPanelTab === 'navigator' }"
              @click="state.leftPanelTab = 'navigator'"
            >
              🗺️ Layers
            </button>
            <button
              type="button"
              class="mb-nav-btn"
              style="flex: 1; padding: 6px 8px; font-size: 12px"
              :class="{ 'is-active': state.leftPanelTab === 'saved_templates' }"
              @click="state.leftPanelTab = 'saved_templates'"
            >
              🧩 Saved
            </button>
          </div>

          <!-- Tab 1: 77 Visual Widgets -->
          <div v-if="state.leftPanelTab === 'widgets'">
            <div style="font-size: 12px; color: var(--mb-ui-muted); margin-bottom: 8px">
              💡 <strong>Drag any block onto the canvas</strong> or click it to insert instantly:
            </div>
            <input
              v-model="state.widgetSearch"
              type="search"
              class="mb-input-ui"
              placeholder="🔍 Search blocks (slider, products, button...)"
              aria-label="Search blocks"
            />
            <select
              v-model="state.widgetCategoryFilter"
              class="mb-select-ui"
              style="margin-top: 8px"
              aria-label="Filter block category"
            >
              <option
                v-for="(label, catKey) in CATEGORY_LABELS"
                :key="catKey"
                :value="catKey"
              >
                {{ label }}
              </option>
            </select>

            <div class="mb-widget-palette-grid">
              <button
                v-for="w in filteredWidgets"
                :key="w.type"
                type="button"
                class="mb-widget-chip"
                :class="{ 'is-coming-soon': comingSoonTypes.has(w.type) }"
                :draggable="!comingSoonTypes.has(w.type)"
                :disabled="comingSoonTypes.has(w.type)"
                :title="comingSoonTypes.has(w.type) ? 'Coming soon: not available on the storefront yet' : undefined"
                :data-widget-type="w.type"
                @dragstart="handleDragStart(w.type, w.label)"
                @dragend="handleDragEnd"
                @click="store.insertWidget(w.type)"
              >
                <span class="mb-widget-emoji">{{ WIDGET_EMOJIS[w.category] || '📦' }}</span>
                <span class="mb-widget-label">{{ w.label }}</span>
                <span v-if="comingSoonTypes.has(w.type)" class="mb-widget-cat-tag mb-coming-soon-tag">Coming soon</span>
                <span v-else class="mb-widget-cat-tag">+ Drag or Click</span>
              </button>
            </div>
          </div>

          <!-- Tab 2: Navigator Layer Tree -->
          <div v-else-if="state.leftPanelTab === 'navigator'">
            <p style="font-size: 12px; color: var(--mb-ui-muted); margin-top: 0">
              Visual page outline. Click any item to select and customize it:
            </p>
            <div
              v-for="(sec, sIdx) in state.currentTemplate.layout.sections"
              :key="sec.id"
              style="margin-bottom: 10px; padding: 10px; border: 1px solid var(--mb-ui-border); border-radius: 10px"
            >
              <div
                style="display: flex; justify-content: space-between; align-items: center; font-weight: 700; cursor: pointer"
                @click="state.selectedSectionId = sec.id"
              >
                <span>📦 {{ sIdx + 1 }}. {{ sec.label }}</span>
                <button
                  type="button"
                  class="mb-mini-btn"
                  @click.stop="store.moveSection(sec.id, -1)"
                >
                  ↑
                </button>
              </div>
              <div
                v-for="col in sec.columns"
                :key="col.id"
                style="padding-left: 10px; margin-top: 6px"
              >
                <div
                  v-for="w in col.widgets"
                  :key="w.id"
                  style="padding: 8px 10px; margin-top: 4px; border-radius: 8px; cursor: pointer; font-size: 12px; font-weight: 600"
                  :style="{
                    background:
                      state.selectedWidgetId === w.id
                        ? 'var(--mb-ui-accent)'
                        : 'var(--mb-ui-surface-alt)',
                    color:
                      state.selectedWidgetId === w.id
                        ? '#ffffff'
                        : 'var(--mb-ui-text)',
                  }"
                  @click="
                    state.selectedSectionId = sec.id;
                    state.selectedColumnId = col.id;
                    state.selectedWidgetId = w.id;
                  "
                >
                  ✨ {{ w.label }}
                </div>
              </div>
            </div>
          </div>

          <!-- Tab 3: Saved Sub-Studio Components -->
          <div v-else>
            <p style="font-size: 12px; color: var(--mb-ui-muted); margin-top: 0">
              Insert your saved MegaMenus, Sliders, Cards, or Accordions:
            </p>
            <div
              v-for="sub in allSubComponents"
              :key="sub.id_template"
              style="padding: 12px; margin-bottom: 10px; border: 1px solid var(--mb-ui-border); border-radius: 10px"
            >
              <div style="font-weight: 700; font-size: 13px">🧩 {{ sub.title }}</div>
              <div style="display: flex; gap: 6px; margin-top: 8px">
                <button
                  type="button"
                  class="mb-btn-ui"
                  style="flex: 1; font-size: 11.5px; padding: 6px"
                  @click="store.insertSavedTemplateIntoCanvas(sub, 'copy')"
                >
                  Insert Copy
                </button>
                <button
                  type="button"
                  class="mb-btn-ui is-primary"
                  style="flex: 1; font-size: 11.5px; padding: 6px"
                  @click="store.insertSavedTemplateIntoCanvas(sub, 'shortcode')"
                >
                  Link Global
                </button>
              </div>
            </div>
          </div>
        </aside>

        <!-- ===============================================================
             CENTER PANE: RICH WYSIWYG CANVAS WITH ANIMATED DROP GUIDES
             =============================================================== -->
        <section
          class="mb-editor-center-pane"
          aria-label="Interactive Live Page Canvas"
        >
          <!-- Interactive Visual Helper Strip -->
          <div class="mb-canvas-helper-banner">
            <span>
              🎯 <strong>Live WYSIWYG Canvas ({{ state.viewport.toUpperCase() }}):</strong>
              Click any block to customize its text, photos, and colors on the right, or drag blocks from the left library.
            </span>
            <span v-if="draggedWidgetLabel" style="color: #1d4ed8; font-weight: 800">
              ⬇ Dragging "{{ draggedWidgetLabel }}" — Drop it into any highlighted blue column zone
            </span>
          </div>

          <div class="mb-canvas-viewport-frame" :data-viewport="state.viewport">
            <div
              v-for="sec in state.currentTemplate.layout.sections"
              :key="sec.id"
              class="mb-canvas-section"
              :class="{ 'is-selected': state.selectedSectionId === sec.id }"
              :style="{
                backgroundColor:
                  sec.bg_color && sec.bg_color !== 'transparent'
                    ? sec.bg_color
                    : '#ffffff',
                paddingTop: (sec.padding_y?.[state.viewport] ?? 20) + 'px',
                paddingBottom: (sec.padding_y?.[state.viewport] ?? 20) + 'px',
              }"
              @click="state.selectedSectionId = sec.id"
            >
              <!-- Friendly Section Quick Bar -->
              <div class="mb-canvas-section-bar">
                <span>
                  📦 Row: <strong>{{ sec.label }}</strong> ({{ sec.columns.length }}
                  {{ sec.columns.length === 1 ? 'Column' : 'Columns' }}
                  <template v-if="sec.reverse_mobile"> • 📲 Reversed on Mobile</template>)
                </span>
                <div style="display: flex; flex-wrap: wrap; gap: 4px">
                  <button
                    type="button"
                    class="mb-mini-btn"
                    title="Move Row Up"
                    @click.stop="store.moveSection(sec.id, -1)"
                  >
                    ↑ Up
                  </button>
                  <button
                    type="button"
                    class="mb-mini-btn"
                    title="Move Row Down"
                    @click.stop="store.moveSection(sec.id, 1)"
                  >
                    ↓ Down
                  </button>
                  <button
                    type="button"
                    class="mb-mini-btn"
                    title="Add Column to Row"
                    @click.stop="
                      state.selectedSectionId = sec.id;
                      store.addColumnToSelectedSection();
                    "
                  >
                    + Column
                  </button>
                  <button
                    type="button"
                    class="mb-mini-btn"
                    title="Reverse column stacking order on Mobile (390px)"
                    @click.stop="
                      store.pushUndoSnapshot();
                      sec.reverse_mobile = !sec.reverse_mobile;
                    "
                  >
                    📲 Flip Mobile
                  </button>
                  <button
                    type="button"
                    class="mb-mini-btn"
                    title="Delete Row"
                    @click.stop="store.removeSection(sec.id)"
                  >
                    🗑️
                  </button>
                </div>
              </div>

              <!-- Columns Row with Visual Drop Indicators -->
              <div
                class="mb-canvas-columns-row"
                :class="{ 'is-reversed-mobile': sec.reverse_mobile }"
              >
                <div
                  v-for="(col, cIdx) in sec.columns"
                  :key="col.id"
                  class="mb-canvas-column"
                  :class="{
                    'is-selected': state.selectedColumnId === col.id,
                    'is-drag-over': dragOverColId === col.id,
                  }"
                  @click.stop="
                    state.selectedSectionId = sec.id;
                    state.selectedColumnId = col.id;
                  "
                  @dragover.prevent="handleDragOverCol(col.id)"
                  @dragleave="
                    () => {
                      if (dragOverColId === col.id) dragOverColId = '';
                    }
                  "
                  @drop.prevent="handleDropOnColumn(col.id)"
                >
                  <!-- Active Visual Drop Placement Guide Banner -->
                  <div
                    v-if="dragOverColId === col.id"
                    class="mb-drop-indicator-pill"
                  >
                    ⬇ Drop here to place "{{
                      draggedWidgetLabel || 'New Block'
                    }}" inside Column {{ cIdx + 1 }}
                  </div>

                  <!-- Rich WYSIWYG Widget Cards -->
                  <div
                    v-for="w in col.widgets"
                    :key="w.id"
                    class="mb-canvas-widget-card"
                    :class="{ 'is-selected': state.selectedWidgetId === w.id }"
                    @click.stop="
                      state.selectedSectionId = sec.id;
                      state.selectedColumnId = col.id;
                      state.selectedWidgetId = w.id;
                    "
                  >
                    <!-- Floating Quick-Action Bar on Selected/Hovered Block -->
                    <div class="mb-widget-quickbar">
                      <span>✨ {{ w.label }}</span>
                      <div style="display: flex; gap: 4px">
                        <button
                          type="button"
                          class="mb-mini-btn"
                          style="min-height: 30px; min-width: 34px; padding: 2px 6px"
                          title="Move Block Up"
                          @click.stop="store.moveWidget(w.id, -1)"
                        >
                          ↑
                        </button>
                        <button
                          type="button"
                          class="mb-mini-btn"
                          style="min-height: 30px; min-width: 34px; padding: 2px 6px"
                          title="Move Block Down"
                          @click.stop="store.moveWidget(w.id, 1)"
                        >
                          ↓
                        </button>
                        <button
                          type="button"
                          class="mb-mini-btn"
                          style="min-height: 30px; min-width: 34px; padding: 2px 6px"
                          title="Duplicate Block"
                          @click.stop="store.duplicateWidget(w.id)"
                        >
                          ⧉
                        </button>
                        <button
                          type="button"
                          class="mb-mini-btn"
                          style="min-height: 30px; min-width: 34px; padding: 2px 6px; color: #b91c1c"
                          title="Remove Block"
                          @click.stop="store.removeWidget(w.id)"
                        >
                          ✕
                        </button>
                      </div>
                    </div>

                    <div
                      v-if="comingSoonTypes.has(w.type)"
                      class="mb-coming-soon-note"
                      role="note"
                    >
                      Coming soon: this block is not shown on your storefront yet.
                    </div>

                    <!-- Real WYSIWYG Visual Preview of the Widget -->
                    <CanvasWidgetPreview
                      :widget="w"
                      :viewport="state.viewport"
                      :category-menu="state.boot.categoryMenu"
                    />
                  </div>

                  <!-- Friendly Visual Drop Zone when Column is Empty -->
                  <div
                    v-if="col.widgets.length === 0"
                    class="mb-empty-col-dropzone"
                  >
                    <span style="font-size: 26px">📥</span>
                    <strong>Empty Column {{ cIdx + 1 }} — Drop any block here</strong>
                    <span style="font-size: 12px">
                      Drag any block from the left panel or quick-insert below:
                    </span>
                    <div style="display: flex; flex-wrap: wrap; justify-content: center; gap: 6px; margin-top: 4px">
                      <button
                        type="button"
                        class="mb-mini-btn"
                        @click.stop="store.insertWidget('heading', col.id)"
                      >
                        + Heading
                      </button>
                      <button
                        type="button"
                        class="mb-mini-btn"
                        @click.stop="store.insertWidget('image_box', col.id)"
                      >
                        + Photo Card
                      </button>
                      <button
                        type="button"
                        class="mb-mini-btn"
                        @click.stop="store.insertWidget('product_grid', col.id)"
                      >
                        + Product Grid
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Visual Section Layout Diagrams Bar (+ Add New Row with Visual Column Previews) -->
            <div class="mb-layout-presets-bar">
              <div style="width: 100%; text-align: center; font-weight: 800; font-size: 13px; color: #1e293b; margin-bottom: 4px">
                ➕ Add New Row to Page (Pick a visual column layout):
              </div>

              <button
                type="button"
                class="mb-layout-diagram-btn"
                @click="store.addSection(1)"
              >
                <div class="mb-diagram-bars">
                  <span style="width: 100%"></span>
                </div>
                <span>1 Column (100%)</span>
              </button>

              <button
                type="button"
                class="mb-layout-diagram-btn"
                @click="store.addSection(2)"
              >
                <div class="mb-diagram-bars">
                  <span style="width: 50%"></span>
                  <span style="width: 50%"></span>
                </div>
                <span>2 Columns (50/50)</span>
              </button>

              <button
                type="button"
                class="mb-layout-diagram-btn"
                @click="store.addSection(3)"
              >
                <div class="mb-diagram-bars">
                  <span style="width: 33.3%"></span>
                  <span style="width: 33.3%"></span>
                  <span style="width: 33.3%"></span>
                </div>
                <span>3 Columns</span>
              </button>

              <button
                type="button"
                class="mb-layout-diagram-btn"
                @click="store.addSection(4)"
              >
                <div class="mb-diagram-bars">
                  <span style="width: 25%"></span>
                  <span style="width: 25%"></span>
                  <span style="width: 25%"></span>
                  <span style="width: 25%"></span>
                </div>
                <span>4 Columns</span>
              </button>
            </div>
          </div>
        </section>

        <!-- ===============================================================
             RIGHT PANE: NON-TECHNICAL VISUAL INSPECTOR (NO RAW CSS REQUIRED!)
             =============================================================== -->
        <aside
          class="mb-editor-right-pane"
          :class="{ 'is-mobile-open': state.rightDrawerOpen }"
          aria-label="Visual Customization Panel"
        >
          <div style="display: flex; gap: 4px; margin-bottom: 12px">
            <button
              type="button"
              class="mb-nav-btn"
              style="flex: 1; padding: 6px 8px; font-size: 12px"
              :class="{ 'is-active': state.rightInspectorTab === 'content' }"
              @click="state.rightInspectorTab = 'content'"
            >
              ✏️ Text & Photos
            </button>
            <button
              type="button"
              class="mb-nav-btn"
              style="flex: 1; padding: 6px 8px; font-size: 12px"
              :class="{ 'is-active': state.rightInspectorTab === 'style' }"
              @click="state.rightInspectorTab = 'style'"
            >
              🎨 Colors & Style
            </button>
            <button
              type="button"
              class="mb-nav-btn"
              style="flex: 1; padding: 6px 8px; font-size: 12px"
              :class="{ 'is-active': state.rightInspectorTab === 'advanced' }"
              @click="state.rightInspectorTab = 'advanced'"
            >
              📐 Spacing & Mobile
            </button>
          </div>

          <div v-if="selectedWidget">
            <div class="mb-inspector-card" style="margin-bottom: 12px">
              <span class="mb-widget-cat-tag">SELECTED BLOCK ON CANVAS</span>
              <div style="font-weight: 800; font-size: 15px; margin-top: 2px">
                ✨ {{ selectedWidget.label }}
              </div>
            </div>

            <!-- ===========================================================
                 TAB 1: CONTENT (TEXTS, PHOTOS, DYNAMIC TAGS, ITEMS)
                 =========================================================== -->
            <div
              v-if="state.rightInspectorTab === 'content'"
              class="mb-inspector-group"
            >
              <div class="mb-inspector-card">
                <label class="mb-field-label">Block Layer Name</label>
                <input
                  v-model="selectedWidget.label"
                  type="text"
                  class="mb-input-ui"
                  @change="store.pushUndoSnapshot()"
                />
              </div>

              <div
                v-if="
                  'title' in selectedWidget.content ||
                  'text' in selectedWidget.content
                "
                class="mb-inspector-card"
              >
                <label class="mb-field-label">Main Headline or Text</label>
                <input
                  v-if="'title' in selectedWidget.content"
                  v-model="selectedWidget.content.title"
                  type="text"
                  class="mb-input-ui"
                  @change="store.pushUndoSnapshot()"
                />
                <input
                  v-else
                  v-model="selectedWidget.content.text"
                  type="text"
                  class="mb-input-ui"
                  @change="store.pushUndoSnapshot()"
                />
              </div>

              <div
                v-if="
                  'subtitle' in selectedWidget.content ||
                  'badge' in selectedWidget.content
                "
                class="mb-inspector-card"
              >
                <label class="mb-field-label">Subtitle or Top Badge</label>
                <input
                  v-if="'subtitle' in selectedWidget.content"
                  v-model="selectedWidget.content.subtitle"
                  type="text"
                  class="mb-input-ui"
                  @change="store.pushUndoSnapshot()"
                />
                <input
                  v-else
                  v-model="selectedWidget.content.badge"
                  type="text"
                  class="mb-input-ui"
                  @change="store.pushUndoSnapshot()"
                />
              </div>

              <div
                v-if="
                  'cta_text' in selectedWidget.content ||
                  'button_text' in selectedWidget.content
                "
                class="mb-inspector-card"
              >
                <label class="mb-field-label">Button Label</label>
                <input
                  v-if="'cta_text' in selectedWidget.content"
                  v-model="selectedWidget.content.cta_text"
                  type="text"
                  class="mb-input-ui"
                  @change="store.pushUndoSnapshot()"
                />
                <input
                  v-else
                  v-model="selectedWidget.content.button_text"
                  type="text"
                  class="mb-input-ui"
                  @change="store.pushUndoSnapshot()"
                />
              </div>

              <div v-if="'html' in selectedWidget.content" class="mb-inspector-card">
                <label class="mb-field-label">Paragraph or Description</label>
                <textarea
                  v-model="selectedWidget.content.html"
                  rows="4"
                  class="mb-textarea-ui"
                  @change="store.pushUndoSnapshot()"
                ></textarea>
              </div>

              <div v-if="'src' in selectedWidget.content" class="mb-inspector-card">
                <label class="mb-field-label">Photo or Image</label>
                <div style="display: flex; gap: 6px; margin-bottom: 8px">
                  <input
                    v-model="selectedWidget.content.src"
                    type="text"
                    class="mb-input-ui"
                    placeholder="https://..."
                    @change="store.pushUndoSnapshot()"
                  />
                  <button
                    type="button"
                    class="mb-btn-ui is-primary"
                    title="Pick photo from Media Gallery"
                    @click="state.activeNav = 'media_manager'"
                  >
                    🖼️ Gallery
                  </button>
                </div>
                <!-- Quick Preset Showroom Photos Dropdown -->
                <label class="mb-field-label" style="font-size: 11px; color: var(--mb-ui-muted)">
                  Or pick a curated showroom photo in 1 click:
                </label>
                <select
                  class="mb-select-ui"
                  @change="
                    (e) => {
                      const val = (e.target as HTMLSelectElement).value;
                      if (val) {
                        store.pushUndoSnapshot();
                        selectedWidget!.content.src = val;
                      }
                    }
                  "
                >
                  <option value="">-- Select sample showroom photo --</option>
                  <option value="https://images.unsplash.com/photo-1555041469-a586c61ea9bc?auto=format&fit=crop&w=1000&q=80">
                    🛋️ Living Room Bouclé Modular Sofa
                  </option>
                  <option value="https://images.unsplash.com/photo-1615873968403-89e068629265?auto=format&fit=crop&w=1000&q=80">
                    🍽️ Dining Room Solid Oak Table
                  </option>
                  <option value="https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=1000&q=80">
                    🛏️ Master Bedroom Upholstered Bed
                  </option>
                  <option value="https://images.unsplash.com/photo-1586023492125-27b2c045efd7?auto=format&fit=crop&w=1000&q=80">
                    🪑 Scandinavian Accent Lounge Chair
                  </option>
                </select>
              </div>

              <!-- Dynamic Store Data Dropdown (Friendly names instead of raw tags) -->
              <div class="mb-inspector-card">
                <label class="mb-field-label">
                  ⚡ Insert Live Store Data Automatically
                </label>
                <select
                  class="mb-select-ui"
                  @change="
                    (e) => {
                      const select = e.target as HTMLSelectElement;
                      if (select.value) {
                        insertDynamicTag(select.value);
                        select.value = '';
                      }
                    }
                  "
                >
                  <option value="">-- Choose live PrestaShop data field --</option>
                  <option
                    v-for="dt in DYNAMIC_TAGS"
                    :key="dt.tag"
                    :value="dt.tag"
                  >
                    {{ dt.label }}
                  </option>
                </select>
              </div>

              <!-- MegaMenu: dedicated editor (source, items, columns, promo) -->
              <MegaMenuEditor
                v-if="selectedWidget.type === 'nav_megamenu'"
                :widget="selectedWidget"
                :category-count="(state.boot.categoryMenu || []).length"
                @before-change="store.pushUndoSnapshot()"
              />

              <!-- Items Repeater (Sliders, Accordions, Hotspots) -->
              <div
                v-else-if="Array.isArray(selectedWidget.content.items)"
                class="mb-inspector-card"
              >
                <div class="mb-field-label">
                  <span>Items List ({{ selectedWidget.content.items.length }})</span>
                  <button
                    type="button"
                    class="mb-btn-ui is-primary"
                    style="min-height: 36px; padding: 4px 10px; font-size: 11.5px"
                    @click="addRepeaterItem()"
                  >
                    + Add Item
                  </button>
                </div>
                <div
                  v-for="(item, idx) in selectedWidget.content.items"
                  :key="idx"
                  style="padding: 10px; margin-bottom: 8px; background: var(--mb-ui-surface); border: 1px solid var(--mb-ui-border); border-radius: 8px"
                >
                  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px">
                    <strong style="font-size: 12px">Item #{{ idx + 1 }}</strong>
                    <button
                      type="button"
                      class="mb-btn-ui is-danger"
                      style="min-height: 32px; padding: 2px 8px; font-size: 11px"
                      @click="removeRepeaterItem(idx)"
                    >
                      Remove
                    </button>
                  </div>
                  <input
                    v-model="item.title"
                    type="text"
                    class="mb-input-ui"
                    placeholder="Title or Question"
                    style="margin-bottom: 6px"
                  />
                  <input
                    v-if="'answer' in item || 'subtitle' in item"
                    v-model="item.answer"
                    type="text"
                    class="mb-input-ui"
                    placeholder="Description or Subcategories"
                  />
                </div>
              </div>
            </div>

            <!-- ===========================================================
                 TAB 2: VISUAL STYLE (1-CLICK SKINS, COLOR PICKERS, DROPDOWNS)
                 =========================================================== -->
            <div
              v-else-if="state.rightInspectorTab === 'style'"
              class="mb-inspector-group"
            >
              <!-- 1-Click Visual Style Presets (Skins) -->
              <div class="mb-inspector-card">
                <label class="mb-field-label">
                  ✨ 1-Click Visual Style Presets
                </label>
                <div class="mb-segmented-group" style="grid-template-columns: 1fr 1fr">
                  <button
                    type="button"
                    class="mb-seg-btn"
                    @click="applyBlockStyleSkin(selectedWidget, 'clean')"
                  >
                    ☀️ Clean Minimal
                  </button>
                  <button
                    type="button"
                    class="mb-seg-btn"
                    @click="applyBlockStyleSkin(selectedWidget, 'card_shadow')"
                  >
                    🃏 Floating Card
                  </button>
                  <button
                    type="button"
                    class="mb-seg-btn"
                    @click="applyBlockStyleSkin(selectedWidget, 'dark_luxury')"
                  >
                    🌙 Dark Showcase
                  </button>
                  <button
                    type="button"
                    class="mb-seg-btn"
                    @click="applyBlockStyleSkin(selectedWidget, 'brand_tint')"
                  >
                    💎 Soft Brand Tint
                  </button>
                  <button
                    type="button"
                    class="mb-seg-btn"
                    style="grid-column: span 2"
                    @click="applyBlockStyleSkin(selectedWidget, 'gold_frame')"
                  >
                    🏛️ Golden Editorial Frame
                  </button>
                </div>
              </div>

              <!-- Visual Color Pickers + Swatch Strip (NO HEX TYPING NEEDED!) -->
              <div class="mb-inspector-card">
                <label class="mb-field-label">🎨 Text Color</label>
                <div class="mb-color-picker-row">
                  <input
                    type="color"
                    class="mb-native-color-input"
                    :value="toHexColor(selectedWidget.style.text_color, '#0f172a')"
                    aria-label="Visual text color picker"
                    @input="
                      (e) => {
                        store.pushUndoSnapshot();
                        selectedWidget!.style.text_color = (
                          e.target as HTMLInputElement
                        ).value;
                      }
                    "
                  />
                  <select
                    v-model="selectedWidget.style.text_color"
                    class="mb-select-ui"
                    @change="store.pushUndoSnapshot()"
                  >
                    <option
                      v-for="sw in CURATED_SWATCHES"
                      :key="sw.color"
                      :value="sw.color"
                    >
                      {{ sw.label }} ({{ sw.color }})
                    </option>
                  </select>
                </div>
                <div class="mb-swatch-strip">
                  <button
                    v-for="sw in CURATED_SWATCHES"
                    :key="sw.color"
                    type="button"
                    class="mb-color-swatch-btn"
                    :style="{ backgroundColor: sw.color }"
                    :title="sw.label"
                    @click="
                      store.pushUndoSnapshot();
                      selectedWidget.style.text_color = sw.color;
                    "
                  ></button>
                </div>
              </div>

              <div class="mb-inspector-card">
                <label class="mb-field-label">🎨 Block Background Color</label>
                <div class="mb-color-picker-row">
                  <input
                    type="color"
                    class="mb-native-color-input"
                    :value="toHexColor(selectedWidget.style.bg_color, '#ffffff')"
                    aria-label="Visual background color picker"
                    @input="
                      (e) => {
                        store.pushUndoSnapshot();
                        selectedWidget!.style.bg_color = (
                          e.target as HTMLInputElement
                        ).value;
                      }
                    "
                  />
                  <select
                    v-model="selectedWidget.style.bg_color"
                    class="mb-select-ui"
                    @change="store.pushUndoSnapshot()"
                  >
                    <option value="transparent">Transparent (No fill)</option>
                    <option
                      v-for="sw in CURATED_SWATCHES"
                      :key="sw.color"
                      :value="sw.color"
                    >
                      {{ sw.label }} ({{ sw.color }})
                    </option>
                  </select>
                </div>
              </div>

              <div class="mb-inspector-card">
                <label class="mb-field-label">🎨 Button & Accent Color</label>
                <div class="mb-color-picker-row">
                  <input
                    type="color"
                    class="mb-native-color-input"
                    :value="toHexColor(selectedWidget.style.accent_color, '#2563eb')"
                    aria-label="Visual accent color picker"
                    @input="
                      (e) => {
                        store.pushUndoSnapshot();
                        selectedWidget!.style.accent_color = (
                          e.target as HTMLInputElement
                        ).value;
                      }
                    "
                  />
                  <select
                    v-model="selectedWidget.style.accent_color"
                    class="mb-select-ui"
                    @change="store.pushUndoSnapshot()"
                  >
                    <option
                      v-for="sw in CURATED_SWATCHES"
                      :key="sw.color"
                      :value="sw.color"
                    >
                      {{ sw.label }} ({{ sw.color }})
                    </option>
                  </select>
                </div>
              </div>

              <!-- Visual Font Size Presets + Slider -->
              <div class="mb-inspector-card">
                <label class="mb-field-label">
                  <span>🔤 Font Size ({{ selectedWidget.style.font_size[state.viewport] }}px)</span>
                  <span class="mb-device-pill">{{ state.viewport }}</span>
                </label>
                <div class="mb-segmented-group" style="margin-bottom: 8px">
                  <button
                    type="button"
                    class="mb-seg-btn"
                    :class="{ 'is-active': selectedWidget.style.font_size[state.viewport] === 14 }"
                    @click="selectedWidget.style.font_size[state.viewport] = 14"
                  >
                    Small
                  </button>
                  <button
                    type="button"
                    class="mb-seg-btn"
                    :class="{ 'is-active': selectedWidget.style.font_size[state.viewport] === 16 }"
                    @click="selectedWidget.style.font_size[state.viewport] = 16"
                  >
                    Normal
                  </button>
                  <button
                    type="button"
                    class="mb-seg-btn"
                    :class="{ 'is-active': selectedWidget.style.font_size[state.viewport] === 22 }"
                    @click="selectedWidget.style.font_size[state.viewport] = 22"
                  >
                    Large
                  </button>
                  <button
                    type="button"
                    class="mb-seg-btn"
                    :class="{ 'is-active': selectedWidget.style.font_size[state.viewport] === 30 }"
                    @click="selectedWidget.style.font_size[state.viewport] = 30"
                  >
                    Hero XL
                  </button>
                </div>
                <input
                  v-model.number="selectedWidget.style.font_size[state.viewport]"
                  type="range"
                  min="12"
                  max="64"
                  style="width: 100%"
                  @change="store.pushUndoSnapshot()"
                />
              </div>

              <!-- Visual Font Weight Dropdown -->
              <div class="mb-inspector-card">
                <label class="mb-field-label">Font Weight</label>
                <select
                  v-model.number="selectedWidget.style.font_weight"
                  class="mb-select-ui"
                  @change="store.pushUndoSnapshot()"
                >
                  <option :value="300">Light (300)</option>
                  <option :value="400">Regular (400)</option>
                  <option :value="500">Medium (500)</option>
                  <option :value="600">Semi-Bold (600)</option>
                  <option :value="700">Bold (700)</option>
                  <option :value="800">Extra-Bold Impact (800)</option>
                </select>
              </div>

              <!-- Visual Alignment Segmented Buttons -->
              <div class="mb-inspector-card">
                <label class="mb-field-label">
                  <span>↔️ Content Alignment</span>
                  <span class="mb-device-pill">{{ state.viewport }}</span>
                </label>
                <div class="mb-segmented-group">
                  <button
                    type="button"
                    class="mb-seg-btn"
                    :class="{ 'is-active': selectedWidget.style.text_align[state.viewport] === 'left' }"
                    @click="selectedWidget.style.text_align[state.viewport] = 'left'"
                  >
                    ⬅ Left
                  </button>
                  <button
                    type="button"
                    class="mb-seg-btn"
                    :class="{ 'is-active': selectedWidget.style.text_align[state.viewport] === 'center' }"
                    @click="selectedWidget.style.text_align[state.viewport] = 'center'"
                  >
                    ↔ Center
                  </button>
                  <button
                    type="button"
                    class="mb-seg-btn"
                    :class="{ 'is-active': selectedWidget.style.text_align[state.viewport] === 'right' }"
                    @click="selectedWidget.style.text_align[state.viewport] = 'right'"
                  >
                    ➡ Right
                  </button>
                </div>
              </div>

              <!-- Grid Columns Per Row Segmented Buttons -->
              <div class="mb-inspector-card">
                <label class="mb-field-label">
                  <span>📦 Grid Columns Per Row</span>
                  <span class="mb-device-pill">{{ state.viewport }}</span>
                </label>
                <div class="mb-segmented-group">
                  <button
                    v-for="n in [1, 2, 3, 4]"
                    :key="n"
                    type="button"
                    class="mb-seg-btn"
                    :class="{ 'is-active': selectedWidget.style.columns_count[state.viewport] === n }"
                    @click="selectedWidget.style.columns_count[state.viewport] = n"
                  >
                    {{ n }} {{ n === 1 ? 'Col' : 'Cols' }}
                  </button>
                </div>
              </div>

              <!-- Box Shadow & Border Radius Dropdowns -->
              <div class="mb-inspector-card">
                <label class="mb-field-label">✨ Card Shadow & Elevation</label>
                <select
                  v-model="selectedWidget.style.shadow"
                  class="mb-select-ui"
                  style="margin-bottom: 10px"
                  @change="store.pushUndoSnapshot()"
                >
                  <option value="none">No Shadow (Flat)</option>
                  <option value="sm">Subtle Soft Shadow</option>
                  <option value="md">Floating Medium Shadow</option>
                  <option value="lg">Deep Luxury Shadow</option>
                  <option value="xl">Brand Accent Glow</option>
                </select>

                <label class="mb-field-label">Rounded Corners</label>
                <select
                  v-model.number="selectedWidget.style.border_radius"
                  class="mb-select-ui"
                  @change="store.pushUndoSnapshot()"
                >
                  <option :value="0">Sharp Corners (0px)</option>
                  <option :value="6">Soft Corners (6px)</option>
                  <option :value="10">Standard Rounded (10px)</option>
                  <option :value="16">Modern Card (16px)</option>
                  <option :value="28">Pill / Capsule (28px)</option>
                </select>
              </div>
            </div>

            <!-- ===========================================================
                 TAB 3: ADVANCED (VISUAL SPACING PRESETS, DEVICE VISIBILITY & CSS DROPDOWN)
                 =========================================================== -->
            <div v-else class="mb-inspector-group">
              <!-- 1-Click Visual Spacing Presets -->
              <div class="mb-inspector-card">
                <label class="mb-field-label">
                  <span>📐 Inner Spacing (Padding)</span>
                  <span class="mb-device-pill">{{ state.viewport }}</span>
                </label>
                <div class="mb-segmented-group" style="margin-bottom: 10px">
                  <button
                    type="button"
                    class="mb-seg-btn"
                    @click="applySpacingPreset(selectedWidget, 'compact')"
                  >
                    Compact
                  </button>
                  <button
                    type="button"
                    class="mb-seg-btn"
                    @click="applySpacingPreset(selectedWidget, 'normal')"
                  >
                    Standard
                  </button>
                  <button
                    type="button"
                    class="mb-seg-btn"
                    @click="applySpacingPreset(selectedWidget, 'spacious')"
                  >
                    Spacious
                  </button>
                  <button
                    type="button"
                    class="mb-seg-btn"
                    @click="applySpacingPreset(selectedWidget, 'xl')"
                  >
                    Extra XL
                  </button>
                </div>

                <label class="mb-field-label" style="font-size: 11.5px">
                  Top & Bottom Padding: {{ selectedWidget.advanced.padding_y[state.viewport] }}px
                </label>
                <input
                  v-model.number="selectedWidget.advanced.padding_y[state.viewport]"
                  type="range"
                  min="0"
                  max="100"
                  style="width: 100%; margin-bottom: 8px"
                />

                <label class="mb-field-label" style="font-size: 11.5px">
                  Left & Right Padding: {{ selectedWidget.advanced.padding_x[state.viewport] }}px
                </label>
                <input
                  v-model.number="selectedWidget.advanced.padding_x[state.viewport]"
                  type="range"
                  min="0"
                  max="100"
                  style="width: 100%"
                />
              </div>

              <!-- Section Background Color Picker -->
              <div v-if="selectedSection" class="mb-inspector-card">
                <label class="mb-field-label">
                  🎨 Entire Row Background ({{ selectedSection.label }})
                </label>
                <div class="mb-color-picker-row">
                  <input
                    type="color"
                    class="mb-native-color-input"
                    :value="toHexColor(selectedSection.bg_color, '#ffffff')"
                    @input="
                      (e) => {
                        store.pushUndoSnapshot();
                        selectedSection!.bg_color = (
                          e.target as HTMLInputElement
                        ).value;
                      }
                    "
                  />
                  <select
                    v-model="selectedSection.bg_color"
                    class="mb-select-ui"
                  >
                    <option value="transparent">Transparent / White</option>
                    <option
                      v-for="sw in CURATED_SWATCHES"
                      :key="sw.color"
                      :value="sw.color"
                    >
                      {{ sw.label }} ({{ sw.color }})
                    </option>
                  </select>
                </div>
              </div>

              <!-- Per-Device Visibility & Mobile Reverse Toggles -->
              <div v-if="selectedSection" class="mb-inspector-card">
                <div class="mb-field-label">📱 Mobile & Tablet Responsive Behavior</div>
                <label style="display: flex; align-items: center; gap: 8px; min-height: 40px; cursor: pointer">
                  <input v-model="selectedSection.reverse_mobile" type="checkbox" />
                  <span>Reverse Column Order on Mobile (390px)</span>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; min-height: 40px; cursor: pointer">
                  <input v-model="selectedWidget.advanced.hide_desktop" type="checkbox" />
                  <span>Hide This Block on Desktop (1440px)</span>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; min-height: 40px; cursor: pointer">
                  <input v-model="selectedWidget.advanced.hide_tablet" type="checkbox" />
                  <span>Hide This Block on Tablet (768px)</span>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; min-height: 40px; cursor: pointer">
                  <input v-model="selectedWidget.advanced.hide_mobile" type="checkbox" />
                  <span>Hide This Block on Mobile (390px)</span>
                </label>
              </div>

              <!-- Visual CSS Effect Preset Dropdown (No Manual CSS Needed!) -->
              <div class="mb-inspector-card">
                <label class="mb-field-label">
                  ✨ Visual Special Effect (Zero CSS Code Needed)
                </label>
                <select
                  class="mb-select-ui"
                  @change="
                    (e) => {
                      const key = (e.target as HTMLSelectElement).value;
                      if (key && VISUAL_CSS_PRESETS[key]) {
                        store.pushUndoSnapshot();
                        selectedWidget!.advanced.custom_css =
                          VISUAL_CSS_PRESETS[key].css;
                      }
                    }
                  "
                >
                  <option
                    v-for="(preset, pKey) in VISUAL_CSS_PRESETS"
                    :key="pKey"
                    :value="pKey"
                  >
                    {{ preset.label }}
                  </option>
                </select>
              </div>
            </div>
          </div>
        </aside>
      </div>
    </main>

    <!-- ===================================================================
         VIEW 4: GLOBAL DESIGN SETTINGS (VISUAL COLOR PICKERS & FONTS DROPDOWNS)
         =================================================================== -->
    <main v-else-if="state.activeNav === 'global_settings'" class="mb-workspace-body">
      <div class="mb-dashboard-toolbar">
        <div style="display: flex; flex-wrap: wrap; gap: 6px">
          <button
            type="button"
            class="mb-nav-btn"
            :class="{ 'is-active': state.globalSettingsTab === 'colors' }"
            @click="state.globalSettingsTab = 'colors'"
          >
            🎨 Brand Color Palette
          </button>
          <button
            type="button"
            class="mb-nav-btn"
            :class="{ 'is-active': state.globalSettingsTab === 'typography' }"
            @click="state.globalSettingsTab = 'typography'"
          >
            🔤 Fonts & Typography
          </button>
          <button
            type="button"
            class="mb-nav-btn"
            :class="{ 'is-active': state.globalSettingsTab === 'layout' }"
            @click="state.globalSettingsTab = 'layout'"
          >
            📐 Screen Widths & Corners
          </button>
          <button
            type="button"
            class="mb-nav-btn"
            :class="{ 'is-active': state.globalSettingsTab === 'page_rules' }"
            @click="state.globalSettingsTab = 'page_rules'"
          >
            🧭 15 Page Section Placements
          </button>
          <button
            type="button"
            class="mb-nav-btn"
            :class="{ 'is-active': state.globalSettingsTab === 'performance' }"
            @click="state.globalSettingsTab = 'performance'"
          >
            ⚡ Speed & Quick-Edit Buttons
          </button>
        </div>
        <div style="display: flex; gap: 8px">
          <button
            type="button"
            class="mb-btn-ui"
            @click="state.showDesignMdModal = true"
          >
            📄 Export / Import DESIGN.md
          </button>
          <button
            type="button"
            class="mb-btn-ui is-primary"
            @click="store.saveGlobalSettings()"
          >
            💾 Save Global Brand Settings
          </button>
        </div>
      </div>

      <!-- Visual Color Pickers for Global Palette -->
      <div v-if="state.globalSettingsTab === 'colors'" class="mb-settings-grid">
        <div
          v-for="(_, colorKey) in state.globalSettings.colors"
          :key="colorKey"
          class="mb-settings-card"
        >
          <label class="mb-field-label">Global Color Token: {{ colorKey.toUpperCase() }}</label>
          <div class="mb-color-picker-row">
            <input
              type="color"
              class="mb-native-color-input"
              :value="toHexColor(state.globalSettings.colors[colorKey], '#2563eb')"
              @input="
                (e) => {
                  state.globalSettings.colors[colorKey] = (
                    e.target as HTMLInputElement
                  ).value;
                }
              "
            />
            <select
              v-model="state.globalSettings.colors[colorKey]"
              class="mb-select-ui"
            >
              <option
                v-for="sw in CURATED_SWATCHES"
                :key="sw.color"
                :value="sw.color"
              >
                {{ sw.label }} ({{ sw.color }})
              </option>
            </select>
          </div>
        </div>
      </div>

      <!-- Visual Typography Dropdowns (No manual font family typing!) -->
      <div v-else-if="state.globalSettingsTab === 'typography'" class="mb-settings-grid">
        <div class="mb-settings-card">
          <label class="mb-field-label">Heading Font Family</label>
          <select v-model="state.globalSettings.typography.heading_font" class="mb-select-ui">
            <option value="Inter, system-ui, sans-serif">Inter — Modern & Clean Sans</option>
            <option value="'Playfair Display', Georgia, serif">Playfair Display — Luxury Editorial Serif</option>
            <option value="Montserrat, sans-serif">Montserrat — Architectural Geometric</option>
            <option value="Lora, Georgia, serif">Lora — Classic Artisan Serif</option>
            <option value="Poppins, sans-serif">Poppins — Friendly Contemporary</option>
          </select>
        </div>
        <div class="mb-settings-card">
          <label class="mb-field-label">Body & Paragraph Font Family</label>
          <select v-model="state.globalSettings.typography.body_font" class="mb-select-ui">
            <option value="Inter, system-ui, sans-serif">Inter — High-Legibility Sans</option>
            <option value="'DM Sans', sans-serif">DM Sans — Minimalist Clean</option>
            <option value="Roboto, sans-serif">Roboto — Universal Standard</option>
            <option value="Lora, Georgia, serif">Lora — Editorial Body</option>
          </select>
        </div>
        <div class="mb-settings-card">
          <label class="mb-field-label">Base Text Size ({{ state.globalSettings.typography.base_size_px }}px)</label>
          <select v-model.number="state.globalSettings.typography.base_size_px" class="mb-select-ui">
            <option :value="14">Compact (14px)</option>
            <option :value="16">Recommended Standard (16px)</option>
            <option :value="18">Spacious Editorial (18px)</option>
          </select>
        </div>
      </div>

      <div v-else-if="state.globalSettingsTab === 'layout'" class="mb-settings-grid">
        <div class="mb-settings-card">
          <label class="mb-field-label">Desktop Maximum Container Width</label>
          <select v-model.number="state.globalSettings.layout.container_width_px" class="mb-select-ui">
            <option :value="1140">Classic Standard (1140px)</option>
            <option :value="1280">Modern Widescreen (1280px — Recommended)</option>
            <option :value="1440">Panoramic Showcase (1440px)</option>
            <option :value="1600">Full Showroom Canvas (1600px)</option>
          </select>
        </div>
        <div class="mb-settings-card">
          <label class="mb-field-label">Global Card Corner Roundness</label>
          <select v-model.number="state.globalSettings.layout.card_radius_px" class="mb-select-ui">
            <option :value="0">Sharp Minimalist (0px)</option>
            <option :value="8">Soft Rounded (8px)</option>
            <option :value="12">Modern Card (12px — Recommended)</option>
            <option :value="20">Extra Rounded (20px)</option>
          </select>
        </div>
      </div>

      <div v-else-if="state.globalSettingsTab === 'page_rules'" class="mb-settings-grid">
        <div
          v-for="(meta, secKey) in state.boot.pageSections"
          :key="secKey"
          class="mb-settings-card"
        >
          <strong>{{ SECTION_ICONS[String(secKey)] || '📄' }} {{ meta.label }}</strong>
          <div style="margin-top: 8px">
            <label class="mb-field-label">Storefront Placement Slot</label>
            <select
              v-if="state.globalSettings.page_rules[secKey]"
              v-model="state.globalSettings.page_rules[secKey].hook_name"
              class="mb-select-ui"
            >
              <option v-for="hk in state.boot.allowedHooks" :key="hk" :value="hk">
                {{ hk }}
              </option>
            </select>
          </div>
        </div>
      </div>

      <div v-else class="mb-settings-grid">
        <div class="mb-settings-card">
          <label style="display: flex; align-items: center; gap: 10px; min-height: 44px; cursor: pointer">
            <input v-model="state.globalSettings.performance.precompile_css" type="checkbox" />
            <span>⚡ Pre-Compile HTML & Responsive CSS on Save (Instant loading for shoppers)</span>
          </label>
          <label style="display: flex; align-items: center; gap: 10px; min-height: 44px; cursor: pointer">
            <input v-model="state.globalSettings.performance.admin_direct_buttons" type="checkbox" />
            <span>✏️ Show "Edit with Mega Builder" Direct Launch Button inside PrestaShop Product, Category & CMS Forms</span>
          </label>
        </div>
      </div>
    </main>

    <!-- ===================================================================
         VIEW 5: STUDIO UI THEME CUSTOMIZER
         =================================================================== -->
    <main v-else-if="state.activeNav === 'studio_theme'" class="mb-workspace-body">
      <div class="mb-dashboard-toolbar">
        <div style="display: flex; flex-wrap: wrap; gap: 8px">
          <button type="button" class="mb-btn-ui" @click="applyThemePreset('nordic_light')">
            ☀️ Nordic Light
          </button>
          <button type="button" class="mb-btn-ui" @click="applyThemePreset('obsidian_pro')">
            🌙 Obsidian Pro Dark
          </button>
          <button type="button" class="mb-btn-ui" @click="applyThemePreset('emerald_craft')">
            🌿 Emerald Craft
          </button>
          <button type="button" class="mb-btn-ui" @click="applyThemePreset('royal_velvet')">
            👑 Royal Velvet
          </button>
        </div>
        <button type="button" class="mb-btn-ui is-primary" @click="store.saveStudioTheme()">
          💾 Save Studio Theme
        </button>
      </div>

      <div class="mb-settings-grid">
        <div class="mb-settings-card">
          <label class="mb-field-label">Studio Header Brand Name</label>
          <input v-model="state.studioTheme.brand_title" type="text" class="mb-input-ui" />
        </div>
        <div class="mb-settings-card">
          <label class="mb-field-label">Studio Color Mode</label>
          <select v-model="state.studioTheme.mode" class="mb-select-ui">
            <option value="light">☀️ Light Mode</option>
            <option value="dark">🌙 Dark Mode</option>
          </select>
        </div>
        <div class="mb-settings-card">
          <label class="mb-field-label">Workspace Control Density</label>
          <select v-model="state.studioTheme.density" class="mb-select-ui">
            <option value="compact">Compact</option>
            <option value="comfortable">Comfortable (Recommended)</option>
            <option value="spacious">Spacious</option>
          </select>
        </div>
      </div>
    </main>

    <!-- ===================================================================
         VIEW 6: BUILT-IN MEDIA MANAGER
         =================================================================== -->
    <main v-else-if="state.activeNav === 'media_manager'" class="mb-workspace-body">
      <div class="mb-dashboard-toolbar">
        <div style="display: flex; flex-wrap: wrap; gap: 8px; align-items: center">
          <button
            type="button"
            class="mb-nav-btn"
            :class="{ 'is-active': state.activeMediaFolder === '' }"
            @click="
              state.activeMediaFolder = '';
              store.refreshMedia('');
            "
          >
            📁 All Folders
          </button>
          <button
            v-for="fld in state.mediaFolders"
            :key="fld"
            type="button"
            class="mb-nav-btn"
            :class="{ 'is-active': state.activeMediaFolder === fld }"
            @click="
              state.activeMediaFolder = fld;
              store.refreshMedia(fld);
            "
          >
            📁 {{ fld }}
          </button>
        </div>

        <div style="display: flex; flex-wrap: wrap; gap: 8px; align-items: center">
          <input
            v-model="state.newFolderName"
            type="text"
            class="mb-input-ui"
            style="max-width: 180px"
            placeholder="New folder name..."
            aria-label="New folder name"
          />
          <button type="button" class="mb-btn-ui" @click="store.createMediaFolder()">
            + Create Folder
          </button>
          <label class="mb-btn-ui is-primary" style="cursor: pointer">
            ⬆️ Upload Photo or SVG Icon
            <input
              type="file"
              accept=".jpg,.jpeg,.png,.webp,.gif,.avif,.svg"
              style="display: none"
              @change="handleFileUploadInput"
            />
          </label>
        </div>
      </div>

      <div class="mb-templates-grid">
        <article
          v-for="asset in state.mediaFiles"
          :key="asset.rel_path"
          class="mb-tpl-card"
        >
          <div>
            <strong>🖼️ {{ asset.name }}</strong>
            <span class="mb-widget-cat-tag" style="display: block">
              {{ asset.ext.toUpperCase() }} • {{ asset.size_bytes }} bytes
            </span>
          </div>
          <div class="mb-tpl-actions">
            <button
              v-if="selectedWidget && 'src' in selectedWidget.content"
              type="button"
              class="mb-btn-ui is-primary"
              @click="
                selectedWidget.content.src = asset.url;
                state.activeNav = 'editor';
              "
            >
              Use in Selected Block
            </button>
            <button
              type="button"
              class="mb-btn-ui is-danger"
              @click="store.deleteMediaFile(asset.rel_path)"
            >
              Delete
            </button>
          </div>
        </article>
      </div>
    </main>

    <!-- ===================================================================
         MODALS: NEW TEMPLATE, LIVE PREVIEW, FINDER, SHORTCUTS, DESIGN.MD
         =================================================================== -->
    <div
      v-if="state.showNewTemplateModal"
      class="mb-modal-backdrop"
      @click.self="state.showNewTemplateModal = false"
    >
      <div
        class="mb-modal-card"
        role="dialog"
        aria-modal="true"
        aria-labelledby="mb-new-tpl-title"
      >
        <div class="mb-modal-header">
          <h2 id="mb-new-tpl-title" style="margin: 0">
            ✨ Create New Page or Reusable Component
          </h2>
          <button
            type="button"
            class="mb-btn-ui"
            @click="state.showNewTemplateModal = false"
          >
            ✕
          </button>
        </div>
        <div class="mb-inspector-group">
          <div>
            <label class="mb-field-label">1. Give your design a clear name</label>
            <input
              v-model="state.newTplForm.title"
              type="text"
              class="mb-input-ui"
              placeholder="e.g. Summer Showroom Homepage"
            />
          </div>
          <div>
            <label class="mb-field-label">2. Which part of your store is this for?</label>
            <select v-model="state.newTplForm.section_type" class="mb-select-ui">
              <optgroup label="15 Storefront Page Sections">
                <option
                  v-for="(meta, secKey) in state.boot.pageSections"
                  :key="secKey"
                  :value="secKey"
                >
                  {{ SECTION_ICONS[String(secKey)] || '📄' }} {{ meta.label }}
                </option>
              </optgroup>
              <optgroup label="4 Reusable Sub-Components">
                <option
                  v-for="(meta, subKey) in state.boot.subComponents"
                  :key="subKey"
                  :value="subKey"
                >
                  🧩 {{ meta.label }}
                </option>
              </optgroup>
            </select>
          </div>
          <label
            style="display: flex; align-items: center; gap: 10px; min-height: 44px; cursor: pointer"
          >
            <input
              v-model="state.newTplForm.use_starter_blueprint"
              type="checkbox"
            />
            <span>
              <strong>Start with a pre-built professional Starter Blueprint</strong>
              (Recommended so you never start from a blank page)
            </span>
          </label>
          <div
            style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 10px"
          >
            <button
              type="button"
              class="mb-btn-ui"
              @click="state.showNewTemplateModal = false"
            >
              Cancel
            </button>
            <button
              type="button"
              class="mb-btn-ui is-primary"
              @click="store.createTemplateFromModal()"
            >
              🎨 Create & Open in Visual Editor
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Live Storefront Preview Modal -->
    <div
      v-if="state.showLivePreviewModal"
      class="mb-modal-backdrop"
      @click.self="state.showLivePreviewModal = false"
    >
      <div
        class="mb-modal-card"
        style="max-width: 1120px"
        role="dialog"
        aria-modal="true"
      >
        <div class="mb-modal-header">
          <h2 style="margin: 0">
            👁️ Live Storefront Preview: {{ state.currentTemplate.title }}
          </h2>
          <button
            type="button"
            class="mb-btn-ui"
            @click="state.showLivePreviewModal = false"
          >
            ✕ Close Preview
          </button>
        </div>
        <component :is="'style'">{{ state.livePreviewCss }}</component>
        <!-- eslint-disable-next-line vue/no-v-html -->
        <div
          style="background: #ffffff; color: #0f172a; padding: 18px; border-radius: 10px; border: 1px solid #cbd5e1"
          v-html="state.livePreviewHtml"
        ></div>
      </div>
    </div>

    <!-- Quick Finder Modal (Ctrl+K) -->
    <div
      v-if="state.showFinderModal"
      class="mb-modal-backdrop"
      @click.self="state.showFinderModal = false"
    >
      <div class="mb-modal-card" role="dialog" aria-modal="true">
        <div class="mb-modal-header">
          <h2 style="margin: 0">🔍 Quick Page & Component Finder</h2>
          <button
            type="button"
            class="mb-btn-ui"
            @click="state.showFinderModal = false"
          >
            ✕
          </button>
        </div>
        <input
          v-model="state.finderQuery"
          type="search"
          class="mb-input-ui"
          placeholder="Type any template or section name..."
        />
        <div
          style="margin-top: 12px; display: flex; flex-direction: column; gap: 6px"
        >
          <button
            v-for="tpl in state.boot.templates.slice(0, 10)"
            :key="tpl.id_template"
            type="button"
            class="mb-btn-ui"
            style="justify-content: space-between"
            @click="
              state.showFinderModal = false;
              store.openTemplateInEditor(tpl.id_template);
            "
          >
            <span>{{ SECTION_ICONS[tpl.section_type] || '📄' }} {{ tpl.title }}</span>
            <span class="mb-widget-cat-tag">{{ tpl.section_type }}</span>
          </button>
        </div>
      </div>
    </div>

    <!-- DESIGN.md Import / Export Modal -->
    <div
      v-if="state.showDesignMdModal"
      class="mb-modal-backdrop"
      @click.self="state.showDesignMdModal = false"
    >
      <div class="mb-modal-card" role="dialog" aria-modal="true">
        <div class="mb-modal-header">
          <h2 style="margin: 0">📄 DESIGN.md Brand Specification</h2>
          <button
            type="button"
            class="mb-btn-ui"
            @click="state.showDesignMdModal = false"
          >
            ✕
          </button>
        </div>
        <textarea
          v-model="state.globalSettings.design_md"
          rows="8"
          class="mb-textarea-ui"
        ></textarea>
        <div
          style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 12px"
        >
          <button
            type="button"
            class="mb-btn-ui is-primary"
            @click="
              state.showDesignMdModal = false;
              store.saveGlobalSettings();
            "
          >
            Save DESIGN.md
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
