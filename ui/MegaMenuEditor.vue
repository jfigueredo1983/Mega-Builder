<script setup lang="ts">
import { computed } from 'vue';
import type { WidgetNode } from './types';
import {
  MAX_COLUMNS,
  MAX_ITEMS,
  emptyColumn,
  emptyItem,
  formatLinks,
  normalizeItems,
  parseLinks,
  type MenuItem,
} from './megamenu';

/**
 * Inspector editor for the MegaMenu widget: menu source (store categories or custom),
 * and for custom menus the items, their columns of links, and an optional promo card.
 * Every change goes through `commit`, which records an undo step first.
 */
const props = defineProps<{
  widget: WidgetNode;
  categoryCount: number;
}>();
const emit = defineEmits<{ (e: 'before-change'): void }>();

const source = computed(() => (props.widget.content.source === 'categories' ? 'categories' : 'manual'));
const items = computed<MenuItem[]>(() => normalizeItems(props.widget.content.items));

function commit(mutate: (items: MenuItem[]) => void) {
  emit('before-change');
  const next = normalizeItems(props.widget.content.items);
  mutate(next);
  props.widget.content.items = next;
}

function setField(key: 'source' | 'max_items' | 'menu_label', value: string | number) {
  emit('before-change');
  props.widget.content[key] = value;
}

function moveItem(idx: number, delta: -1 | 1) {
  commit((list) => {
    const target = idx + delta;
    if (target < 0 || target >= list.length) return;
    const [it] = list.splice(idx, 1);
    list.splice(target, 0, it);
  });
}
</script>

<template>
  <div class="mb-inspector-card mb-mm-editor">
    <label class="mb-field-label" for="mb-mm-label">Menu name (read by screen readers, shown on the mobile button)</label>
    <input
      id="mb-mm-label"
      class="mb-input-ui"
      type="text"
      :value="widget.content.menu_label || 'Menu'"
      @change="setField('menu_label', ($event.target as HTMLInputElement).value)"
    />

    <fieldset class="mb-mm-source">
      <legend class="mb-field-label">Menu items come from</legend>
      <label>
        <input type="radio" name="mb-mm-source" value="categories" :checked="source === 'categories'" @change="setField('source', 'categories')" />
        Store categories (always up to date)
      </label>
      <label>
        <input type="radio" name="mb-mm-source" value="manual" :checked="source === 'manual'" @change="setField('source', 'manual')" />
        Custom menu
      </label>
    </fieldset>

    <template v-if="source === 'categories'">
      <label class="mb-field-label" for="mb-mm-max">Top-level categories to show</label>
      <input
        id="mb-mm-max"
        class="mb-input-ui"
        type="number"
        min="1"
        :max="MAX_ITEMS"
        :value="widget.content.max_items || 8"
        @change="setField('max_items', Math.min(MAX_ITEMS, Math.max(1, Number(($event.target as HTMLInputElement).value) || 8)))"
      />
      <p class="mb-mm-hint">
        Top categories become menu items, their subcategories become columns, and the level below becomes links.
        Your store has {{ categoryCount }} top-level categories right now.
      </p>
    </template>

    <template v-else>
      <div class="mb-field-label">
        <span>Menu items ({{ items.length }}/{{ MAX_ITEMS }})</span>
        <button
          type="button"
          class="mb-btn-ui is-primary mb-mm-add-item"
          :disabled="items.length >= MAX_ITEMS"
          @click="commit((l) => l.push(emptyItem(l.length + 1)))"
        >
          + Add item
        </button>
      </div>

      <details v-for="(item, i) in items" :key="i" class="mb-mm-item-editor" :open="i === 0">
        <summary>
          <strong>{{ item.title }}</strong>
          <span class="mb-mm-meta">{{ item.columns.length }} column{{ item.columns.length === 1 ? '' : 's' }}</span>
        </summary>

        <div class="mb-mm-row">
          <label>Label<input class="mb-input-ui" type="text" :value="item.title" @change="commit((l) => (l[i].title = ($event.target as HTMLInputElement).value.trim() || l[i].title))" /></label>
          <label>Link<input class="mb-input-ui" type="text" placeholder="/category or https://…" :value="item.url" @change="commit((l) => (l[i].url = ($event.target as HTMLInputElement).value.trim()))" /></label>
          <label>Badge<input class="mb-input-ui" type="text" placeholder="e.g. NEW" :value="item.badge" @change="commit((l) => (l[i].badge = ($event.target as HTMLInputElement).value.trim()))" /></label>
        </div>

        <div v-for="(col, c) in item.columns" :key="c" class="mb-mm-col-editor">
          <div class="mb-mm-row">
            <label>Column heading<input class="mb-input-ui" type="text" :value="col.heading" @change="commit((l) => (l[i].columns[c].heading = ($event.target as HTMLInputElement).value.trim()))" /></label>
            <label>Heading link<input class="mb-input-ui" type="text" :value="col.url" @change="commit((l) => (l[i].columns[c].url = ($event.target as HTMLInputElement).value.trim()))" /></label>
          </div>
          <label class="mb-mm-links-label">
            Links, one per line as <code>Label | /url</code>
            <textarea
              class="mb-input-ui"
              rows="4"
              :value="formatLinks(col.links)"
              @change="commit((l) => (l[i].columns[c].links = parseLinks(($event.target as HTMLTextAreaElement).value)))"
            ></textarea>
          </label>
          <button type="button" class="mb-btn-ui is-danger mb-mm-small" @click="commit((l) => l[i].columns.splice(c, 1))">Remove column</button>
        </div>

        <button
          v-if="item.columns.length < MAX_COLUMNS"
          type="button"
          class="mb-btn-ui mb-mm-small"
          @click="commit((l) => l[i].columns.push(emptyColumn()))"
        >
          + Add column
        </button>

        <details class="mb-mm-promo-editor">
          <summary>Promo card (optional)</summary>
          <label>Title<input class="mb-input-ui" type="text" :value="item.promo_title" @change="commit((l) => (l[i].promo_title = ($event.target as HTMLInputElement).value.trim()))" /></label>
          <label>Image URL<input class="mb-input-ui" type="text" :value="item.promo_image" @change="commit((l) => (l[i].promo_image = ($event.target as HTMLInputElement).value.trim()))" /></label>
          <label>Link<input class="mb-input-ui" type="text" :value="item.promo_url" @change="commit((l) => (l[i].promo_url = ($event.target as HTMLInputElement).value.trim()))" /></label>
        </details>

        <div class="mb-mm-item-actions">
          <button type="button" class="mb-btn-ui mb-mm-small" :disabled="i === 0" @click="moveItem(i, -1)">↑ Up</button>
          <button type="button" class="mb-btn-ui mb-mm-small" :disabled="i === items.length - 1" @click="moveItem(i, 1)">↓ Down</button>
          <button type="button" class="mb-btn-ui is-danger mb-mm-small" @click="commit((l) => l.splice(i, 1))">Remove item</button>
        </div>
      </details>
    </template>
  </div>
</template>

<style scoped>
.mb-mm-source {
  display: grid;
  gap: 6px;
  margin: 10px 0;
  padding: 0;
  border: 0;
}
.mb-mm-source label {
  display: flex;
  gap: 8px;
  align-items: center;
  font-size: 13px;
}
.mb-mm-hint {
  margin: 8px 0 0;
  font-size: 12px;
  color: var(--mb-ui-muted);
}
.mb-mm-item-editor,
.mb-mm-col-editor {
  margin-top: 8px;
  padding: 10px;
  border: 1px solid var(--mb-ui-border);
  border-radius: 8px;
  background: var(--mb-ui-surface);
}
.mb-mm-item-editor > summary {
  display: flex;
  justify-content: space-between;
  gap: 8px;
  cursor: pointer;
  font-size: 13px;
}
.mb-mm-meta {
  color: var(--mb-ui-muted);
  font-size: 11px;
}
.mb-mm-row {
  display: grid;
  gap: 6px;
  margin-top: 8px;
}
.mb-mm-row label,
.mb-mm-links-label,
.mb-mm-promo-editor label {
  display: grid;
  gap: 4px;
  font-size: 11.5px;
  font-weight: 700;
}
.mb-mm-promo-editor {
  margin-top: 8px;
  font-size: 12px;
}
.mb-mm-item-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin-top: 10px;
}
.mb-mm-small,
.mb-mm-add-item {
  min-height: 34px;
  padding: 4px 10px;
  font-size: 11.5px;
}
</style>
