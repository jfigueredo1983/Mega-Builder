/**
 * Mega Builder — Stage 6 Reactive Composable Store (useStudioStore)
 * Manages the 3-Pane Visual Studio, 15 Page Sections, 4 Sub-Studios,
 * 3-Viewport Responsive Mode (1440px / 768px / 390px), and 50-Step Undo/Redo Stack.
 */

import { reactive, computed } from 'vue';
import type {
  BootPayload,
  BreakpointMode,
  ColumnNode,
  GlobalSettings,
  LayoutTree,
  MediaAsset,
  ResponsiveNumber,
  SectionNode,
  StudioTheme,
  TemplateRecord,
  WidgetDefinition,
  WidgetNode,
} from './types';
import { callMegaBuilderApi } from './apiClient';

const MAX_UNDO_STEPS = 50;

function deepClone<T>(obj: T): T {
  return JSON.parse(JSON.stringify(obj));
}

function makeResponsiveInt(val: number): ResponsiveNumber {
  return { desktop: val, tablet: val, mobile: val };
}

export function createStudioStore(boot: BootPayload) {
  const state = reactive({
    boot,
    activeNav: 'dashboard' as
      | 'dashboard'
      | 'editor'
      | 'sub_studios'
      | 'global_settings'
      | 'studio_theme'
      | 'media_manager',
    activeSectionFilter: (boot.initialSection || 'all') as string,
    activeSubStudioKind: 'megamenu' as 'megamenu' | 'slider' | 'card' | 'accordion',
    statusFilter: 'all' as 'all' | 'active' | 'draft',
    dashboardSearch: '',

    // Studio Editor state
    viewport: 'desktop' as BreakpointMode,
    leftDrawerOpen: false,
    rightDrawerOpen: false,
    leftPanelTab: 'widgets' as 'widgets' | 'navigator' | 'saved_templates',
    rightInspectorTab: 'content' as 'content' | 'style' | 'advanced',
    widgetSearch: '',
    widgetCategoryFilter: 'all' as string,

    currentTemplate: {
      id_template: 0,
      entity_kind: 'page_template',
      section_type: 'homepage',
      hook_name: 'displayHome',
      scope_rule: 'all',
      scope_targets: [],
      active: 0,
      priority: 10,
      title: 'Flagship Architectural Furniture Homepage',
      layout: deepClone(
        boot.blueprints?.homepage?.layout || { version: '1.0', sections: [] }
      ),
      revisions: [],
    } as TemplateRecord & { layout: LayoutTree },

    selectedSectionId: '' as string,
    selectedColumnId: '' as string,
    selectedWidgetId: '' as string,
    draggedWidgetType: '' as string,

    // Undo / Redo History Stack
    undoStack: [] as string[],
    redoStack: [] as string[],
    isDirty: false,
    isSaving: false,
    toastMessage: '',
    toastType: 'success' as 'success' | 'error' | 'info',

    // Global Settings & Theme
    globalSettings: deepClone(boot.globalSettings) as GlobalSettings,
    globalSettingsTab: 'colors' as
      | 'colors'
      | 'typography'
      | 'layout'
      | 'components'
      | 'lightbox'
      | 'page_rules'
      | 'performance',
    studioTheme: deepClone(boot.studioTheme) as StudioTheme,

    // Media Manager
    mediaFolders: [...(boot.media?.folders || ['showroom', 'banners', 'icons'])],
    mediaFiles: [...(boot.media?.files || [])] as MediaAsset[],
    activeMediaFolder: '',
    mediaSearch: '',
    newFolderName: '',

    // Modals
    showNewTemplateModal: false,
    newTplForm: {
      entity_kind: 'page_template' as 'page_template' | 'sub_component',
      section_type: 'homepage',
      title: '',
      hook_name: 'displayHome',
      scope_rule: 'all' as 'all' | 'specific_ids' | 'conditions',
      use_starter_blueprint: true,
    },
    showLivePreviewModal: false,
    livePreviewHtml: '',
    livePreviewCss: '',
    showFinderModal: false,
    finderQuery: '',
    showShortcutsModal: false,
    showDesignMdModal: false,
    showMediaPickerModal: false,
    mediaPickerTargetProp: 'src' as string,
  });

  function notify(msg: string, type: 'success' | 'error' | 'info' = 'success') {
    state.toastMessage = msg;
    state.toastType = type;
  }

  function pushUndoSnapshot() {
    const snap = JSON.stringify(state.currentTemplate.layout);
    if (
      state.undoStack.length > 0 &&
      state.undoStack[state.undoStack.length - 1] === snap
    ) {
      return;
    }
    state.undoStack.push(snap);
    if (state.undoStack.length > MAX_UNDO_STEPS) {
      state.undoStack.shift();
    }
    state.redoStack = [];
    state.isDirty = true;
  }

  function undo() {
    if (state.undoStack.length === 0) return;
    const currentSnap = JSON.stringify(state.currentTemplate.layout);
    state.redoStack.push(currentSnap);
    const prevSnap = state.undoStack.pop()!;
    state.currentTemplate.layout = JSON.parse(prevSnap);
    state.isDirty = true;
    notify('Undid last canvas change.', 'info');
  }

  function redo() {
    if (state.redoStack.length === 0) return;
    const currentSnap = JSON.stringify(state.currentTemplate.layout);
    state.undoStack.push(currentSnap);
    const nextSnap = state.redoStack.pop()!;
    state.currentTemplate.layout = JSON.parse(nextSnap);
    state.isDirty = true;
    notify('Redid canvas change.', 'info');
  }

  // Computed getters
  const filteredDashboardTemplates = computed(() => {
    const q = state.dashboardSearch.trim().toLowerCase();
    return state.boot.templates.filter((t) => {
      if (t.entity_kind !== 'page_template') return false;
      if (
        state.activeSectionFilter !== 'all' &&
        t.section_type !== state.activeSectionFilter
      ) {
        return false;
      }
      if (state.statusFilter === 'active' && Number(t.active) !== 1) return false;
      if (state.statusFilter === 'draft' && Number(t.active) !== 0) return false;
      if (
        q &&
        !t.title.toLowerCase().includes(q) &&
        !t.section_type.toLowerCase().includes(q)
      ) {
        return false;
      }
      return true;
    });
  });

  const subStudioTemplates = computed(() => {
    return state.boot.templates.filter(
      (t) =>
        t.entity_kind === 'sub_component' &&
        t.section_type === state.activeSubStudioKind
    );
  });

  const allSubComponents = computed(() => {
    return state.boot.templates.filter((t) => t.entity_kind === 'sub_component');
  });

  const filteredWidgets = computed(() => {
    const q = state.widgetSearch.trim().toLowerCase();
    return state.boot.widgets.filter((w) => {
      if (
        state.widgetCategoryFilter !== 'all' &&
        w.category !== state.widgetCategoryFilter
      ) {
        return false;
      }
      if (
        q &&
        !w.label.toLowerCase().includes(q) &&
        !w.type.toLowerCase().includes(q)
      ) {
        return false;
      }
      return true;
    });
  });

  const selectedSection = computed<SectionNode | null>(() => {
    const secs = state.currentTemplate.layout?.sections || [];
    return (
      secs.find((s) => s.id === state.selectedSectionId) || secs[0] || null
    );
  });

  const selectedWidget = computed<WidgetNode | null>(() => {
    const secs = state.currentTemplate.layout?.sections || [];
    for (const s of secs) {
      for (const c of s.columns) {
        for (const w of c.widgets) {
          if (w.id === state.selectedWidgetId) {
            return w;
          }
        }
      }
    }
    return null;
  });

  function ensureSelection() {
    const secs = state.currentTemplate.layout?.sections || [];
    if (secs.length === 0) {
      state.selectedSectionId = '';
      state.selectedColumnId = '';
      state.selectedWidgetId = '';
      return;
    }
    const firstSec = secs[0];
    if (!secs.some((s) => s.id === state.selectedSectionId)) {
      state.selectedSectionId = firstSec.id;
    }
    const sec = secs.find((s) => s.id === state.selectedSectionId) || firstSec;
    if (sec.columns.length > 0) {
      if (!sec.columns.some((c) => c.id === state.selectedColumnId)) {
        state.selectedColumnId = sec.columns[0].id;
      }
      const col =
        sec.columns.find((c) => c.id === state.selectedColumnId) ||
        sec.columns[0];
      if (col.widgets.length > 0 && !selectedWidget.value) {
        state.selectedWidgetId = col.widgets[0].id;
      }
    }
  }

  async function openTemplateInEditor(idTemplate: number) {
    const res = await callMegaBuilderApi<{ template: TemplateRecord }>(
      state.boot.ajaxUrl,
      state.boot.csrfToken,
      'getTemplate',
      { id_template: idTemplate }
    );
    if (res.ok && res.data.template) {
      const tpl = res.data.template;
      state.currentTemplate = {
        ...tpl,
        layout: deepClone(
          tpl.layout || { version: '1.0', sections: [] }
        ),
      };
      state.undoStack = [];
      state.redoStack = [];
      state.isDirty = false;
      ensureSelection();
      state.activeNav = 'editor';
      notify(`Loaded "${tpl.title}" into Visual Studio.`, 'info');
    } else {
      notify(res.message || 'Could not load template.', 'error');
    }
  }

  function startFromBlueprint(sectionOrComponentKey: string, entityKind: 'page_template' | 'sub_component' = 'page_template') {
    const bp = state.boot.blueprints[sectionOrComponentKey];
    const meta =
      entityKind === 'sub_component'
        ? state.boot.subComponents[sectionOrComponentKey]
        : state.boot.pageSections[sectionOrComponentKey];

    state.currentTemplate = {
      id_template: 0,
      entity_kind: bp?.entity_kind || entityKind,
      section_type: sectionOrComponentKey,
      hook_name: bp?.hook_name || meta?.default_hook || 'displayHome',
      scope_rule: 'all',
      scope_targets: [],
      active: 0,
      priority: 10,
      title: bp?.title || `New ${meta?.label || sectionOrComponentKey} Template`,
      layout: deepClone(
        bp?.layout || {
          version: '1.0',
          sections: [],
        }
      ),
      revisions: [],
    };
    state.undoStack = [];
    state.redoStack = [];
    state.isDirty = true;
    ensureSelection();
    state.activeNav = 'editor';
    notify(`Loaded 1-Click Starter Blueprint for ${meta?.label || sectionOrComponentKey}.`, 'info');
  }

  async function createTemplateFromModal() {
    const f = state.newTplForm;
    const bp = state.boot.blueprints[f.section_type];
    const defaultHook =
      f.entity_kind === 'sub_component'
        ? state.boot.subComponents[f.section_type]?.default_hook
        : state.boot.pageSections[f.section_type]?.default_hook;

    const layout: LayoutTree =
      f.use_starter_blueprint && bp?.layout
        ? deepClone(bp.layout)
        : {
            version: '1.0',
            sections: [
              {
                id: 'sec_' + Date.now(),
                label: 'Main Section',
                layout_mode: 'boxed',
                max_width: 1280,
                gap: makeResponsiveInt(24),
                padding_y: { desktop: 40, tablet: 28, mobile: 20 },
                padding_x: { desktop: 20, tablet: 16, mobile: 14 },
                bg_color: 'transparent',
                bg_image: '',
                reverse_mobile: false,
                hide_desktop: false,
                hide_tablet: false,
                hide_mobile: false,
                columns: [
                  {
                    id: 'col_' + Date.now(),
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
          };

    state.currentTemplate = {
      id_template: 0,
      entity_kind: f.entity_kind,
      section_type: f.section_type,
      hook_name: f.hook_name || defaultHook || 'displayHome',
      scope_rule: f.scope_rule,
      scope_targets: [],
      active: 0,
      priority: 10,
      title:
        f.title.trim() ||
        bp?.title ||
        `Custom ${f.section_type.toUpperCase()} Layout`,
      layout,
      revisions: [],
    };
    state.showNewTemplateModal = false;
    state.undoStack = [];
    state.redoStack = [];
    state.isDirty = true;
    ensureSelection();
    state.activeNav = 'editor';
    await saveCurrentTemplate();
  }

  async function saveCurrentTemplate(publishOverride?: number) {
    if (state.isSaving) return;
    state.isSaving = true;
    if (typeof publishOverride === 'number') {
      state.currentTemplate.active = publishOverride;
    }

    const res = await callMegaBuilderApi<{
      template: TemplateRecord;
      templates: TemplateRecord[];
    }>(state.boot.ajaxUrl, state.boot.csrfToken, 'saveTemplate', {
      ...state.currentTemplate,
      revision_label:
        state.currentTemplate.active === 1
          ? 'Published Checkpoint'
          : 'Saved Draft Checkpoint',
    });

    state.isSaving = false;
    if (res.ok && res.data.template) {
      state.currentTemplate.id_template = res.data.template.id_template;
      state.currentTemplate.revisions = res.data.template.revisions || [];
      if (Array.isArray(res.data.templates)) {
        state.boot.templates = res.data.templates;
      }
      state.isDirty = false;
      notify(res.message || 'Template saved & compiled successfully.', 'success');
    } else {
      notify(res.message || 'Validation failed while saving template.', 'error');
    }
  }

  async function toggleTemplateStatus(tpl: TemplateRecord) {
    const nextState = Number(tpl.active) === 1 ? 0 : 1;
    const res = await callMegaBuilderApi<{ templates: TemplateRecord[] }>(
      state.boot.ajaxUrl,
      state.boot.csrfToken,
      'toggleTemplateStatus',
      {
        id_template: tpl.id_template,
        active: nextState,
      }
    );
    if (res.ok) {
      tpl.active = nextState;
      if (Array.isArray(res.data.templates)) {
        state.boot.templates = res.data.templates;
      }
      notify(
        nextState === 1
          ? `"${tpl.title}" is now Published Live!`
          : `"${tpl.title}" switched to Safe Draft.`,
        'success'
      );
    }
  }

  async function duplicateTemplate(idTemplate: number) {
    const res = await callMegaBuilderApi<{
      template: TemplateRecord;
      templates: TemplateRecord[];
    }>(state.boot.ajaxUrl, state.boot.csrfToken, 'duplicateTemplate', {
      id_template: idTemplate,
    });
    if (res.ok && Array.isArray(res.data.templates)) {
      state.boot.templates = res.data.templates;
      notify('Template duplicated as Draft.', 'success');
    }
  }

  async function deleteTemplate(idTemplate: number) {
    const res = await callMegaBuilderApi<{ templates: TemplateRecord[] }>(
      state.boot.ajaxUrl,
      state.boot.csrfToken,
      'deleteTemplate',
      { id_template: idTemplate }
    );
    if (res.ok && Array.isArray(res.data.templates)) {
      state.boot.templates = res.data.templates;
      notify('Template deleted.', 'info');
    }
  }

  async function restoreRevision(idRevision: number) {
    if (!state.currentTemplate.id_template) return;
    const res = await callMegaBuilderApi<{ template: TemplateRecord }>(
      state.boot.ajaxUrl,
      state.boot.csrfToken,
      'restoreRevision',
      {
        id_template: state.currentTemplate.id_template,
        id_revision: idRevision,
      }
    );
    if (res.ok && res.data.template) {
      state.currentTemplate = {
        ...res.data.template,
        layout: deepClone(
          res.data.template.layout || { version: '1.0', sections: [] }
        ),
      };
      ensureSelection();
      notify(`Restored Revision #${idRevision}.`, 'success');
    }
  }

  async function openLivePreview() {
    const res = await callMegaBuilderApi<{ html: string; css: string }>(
      state.boot.ajaxUrl,
      state.boot.csrfToken,
      'previewTemplate',
      {
        layout: state.currentTemplate.layout,
      }
    );
    if (res.ok) {
      state.livePreviewHtml = res.data.html || '';
      state.livePreviewCss = res.data.css || '';
      state.showLivePreviewModal = true;
    } else {
      notify('Could not render preview.', 'error');
    }
  }

  // Canvas Section / Column / Widget manipulation
  function addSection(columnCount = 1) {
    pushUndoSnapshot();
    const secId = 'sec_' + Date.now() + '_' + Math.random().toString(36).slice(2, 5);
    const colWidth = Number((100 / columnCount).toFixed(2));
    const cols: ColumnNode[] = [];
    for (let i = 0; i < columnCount; i++) {
      cols.push({
        id: `${secId}_col_${i + 1}`,
        width: colWidth,
        width_tablet: columnCount > 2 ? 50 : colWidth,
        width_mobile: 100,
        align_items: 'stretch',
        padding: { desktop: 12, tablet: 10, mobile: 8 },
        bg_color: 'transparent',
        border_radius: 0,
        widgets: [],
      });
    }
    const newSec: SectionNode = {
      id: secId,
      label: `Section ${(state.currentTemplate.layout.sections?.length || 0) + 1}`,
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
      columns: cols,
    };
    state.currentTemplate.layout.sections.push(newSec);
    state.selectedSectionId = newSec.id;
    state.selectedColumnId = cols[0].id;
  }

  function addColumnToSelectedSection() {
    const sec = selectedSection.value;
    if (!sec || sec.columns.length >= 6) return;
    pushUndoSnapshot();
    const newCount = sec.columns.length + 1;
    const evenWidth = Number((100 / newCount).toFixed(2));
    sec.columns.forEach((c) => {
      c.width = evenWidth;
    });
    const newCol: ColumnNode = {
      id: `${sec.id}_col_${Date.now().toString(36)}`,
      width: evenWidth,
      width_tablet: 50,
      width_mobile: 100,
      align_items: 'stretch',
      padding: { desktop: 12, tablet: 10, mobile: 8 },
      bg_color: 'transparent',
      border_radius: 0,
      widgets: [],
    };
    sec.columns.push(newCol);
    state.selectedColumnId = newCol.id;
  }

  function buildWidgetNode(def: WidgetDefinition): WidgetNode {
    return {
      id: 'w_' + Date.now() + '_' + Math.random().toString(36).slice(2, 6),
      type: def.type,
      label: def.label,
      content: deepClone(def.defaults || {}),
      style: {
        text_color: '#0f172a',
        bg_color: 'transparent',
        accent_color: state.globalSettings.colors.primary || '#2563eb',
        border_color: '#e2e8f0',
        font_size: { desktop: 16, tablet: 15, mobile: 15 },
        font_weight: 500,
        line_height: 1.5,
        text_align: { desktop: 'left', tablet: 'left', mobile: 'left' },
        border_radius: 10,
        border_width: 0,
        columns_count: { desktop: 4, tablet: 2, mobile: 1 },
        shadow: 'none',
      },
      advanced: {
        padding_y: { desktop: 12, tablet: 10, mobile: 8 },
        padding_x: { desktop: 14, tablet: 12, mobile: 10 },
        margin_y: { desktop: 0, tablet: 0, mobile: 0 },
        z_index: 1,
        hide_desktop: false,
        hide_tablet: false,
        hide_mobile: false,
        custom_class: '',
        custom_css: '',
      },
    };
  }

  function insertWidget(widgetType: string, targetColId?: string, extraContent: Record<string, any> = {}) {
    const def =
      state.boot.widgets.find((w) => w.type === widgetType) ||
      state.boot.widgets[0];
    if (!def) return;

    if (state.currentTemplate.layout.sections.length === 0) {
      addSection(1);
    }

    pushUndoSnapshot();
    const node = buildWidgetNode(def);
    Object.assign(node.content, deepClone(extraContent));

    let destCol: ColumnNode | null = null;
    for (const s of state.currentTemplate.layout.sections) {
      for (const c of s.columns) {
        if (c.id === (targetColId || state.selectedColumnId)) {
          destCol = c;
          state.selectedSectionId = s.id;
          break;
        }
      }
    }
    if (!destCol) {
      const firstSec = state.currentTemplate.layout.sections[0];
      destCol = firstSec.columns[0];
      state.selectedSectionId = firstSec.id;
      state.selectedColumnId = destCol.id;
    }

    destCol.widgets.push(node);
    state.selectedWidgetId = node.id;
    notify(`Added "${node.label}" to canvas.`, 'info');
  }

  function insertSavedTemplateIntoCanvas(tpl: TemplateRecord, mode: 'copy' | 'shortcode') {
    if (mode === 'shortcode') {
      insertWidget('template_widget', undefined, {
        template_id: tpl.id_template,
        component_kind: tpl.section_type,
        title: `Linked Component: ${tpl.title} (#${tpl.id_template})`,
      });
      return;
    }
    // Independent Copy mode: append sections from template
    callMegaBuilderApi<{ template: TemplateRecord }>(
      state.boot.ajaxUrl,
      state.boot.csrfToken,
      'getTemplate',
      { id_template: tpl.id_template }
    ).then((res) => {
      if (res.ok && res.data.template?.layout?.sections) {
        pushUndoSnapshot();
        const clonedSecs = deepClone(res.data.template.layout.sections);
        clonedSecs.forEach((s) => {
          s.id = 'sec_copy_' + Math.random().toString(36).slice(2, 8);
          s.columns.forEach((c) => {
            c.id = s.id + '_col_' + Math.random().toString(36).slice(2, 6);
            c.widgets.forEach((w) => {
              w.id = 'w_copy_' + Math.random().toString(36).slice(2, 8);
            });
          });
          state.currentTemplate.layout.sections.push(s);
        });
        ensureSelection();
        notify(`Inserted copy of "${tpl.title}".`, 'success');
      }
    });
  }

  function moveWidget(widgetId: string, delta: -1 | 1) {
    for (const s of state.currentTemplate.layout.sections) {
      for (const c of s.columns) {
        const idx = c.widgets.findIndex((w) => w.id === widgetId);
        if (idx !== -1) {
          const targetIdx = idx + delta;
          if (targetIdx < 0 || targetIdx >= c.widgets.length) return;
          pushUndoSnapshot();
          const [item] = c.widgets.splice(idx, 1);
          c.widgets.splice(targetIdx, 0, item);
          return;
        }
      }
    }
  }

  function duplicateWidget(widgetId: string) {
    for (const s of state.currentTemplate.layout.sections) {
      for (const c of s.columns) {
        const idx = c.widgets.findIndex((w) => w.id === widgetId);
        if (idx !== -1) {
          pushUndoSnapshot();
          const clone = deepClone(c.widgets[idx]);
          clone.id = 'w_' + Date.now() + '_' + Math.random().toString(36).slice(2, 5);
          clone.label = clone.label + ' (Copy)';
          c.widgets.splice(idx + 1, 0, clone);
          state.selectedWidgetId = clone.id;
          return;
        }
      }
    }
  }

  function removeWidget(widgetId: string) {
    for (const s of state.currentTemplate.layout.sections) {
      for (const c of s.columns) {
        const idx = c.widgets.findIndex((w) => w.id === widgetId);
        if (idx !== -1) {
          pushUndoSnapshot();
          c.widgets.splice(idx, 1);
          ensureSelection();
          return;
        }
      }
    }
  }

  function moveSection(secId: string, delta: -1 | 1) {
    const secs = state.currentTemplate.layout.sections;
    const idx = secs.findIndex((s) => s.id === secId);
    if (idx === -1) return;
    const targetIdx = idx + delta;
    if (targetIdx < 0 || targetIdx >= secs.length) return;
    pushUndoSnapshot();
    const [sec] = secs.splice(idx, 1);
    secs.splice(targetIdx, 0, sec);
  }

  function removeSection(secId: string) {
    const secs = state.currentTemplate.layout.sections;
    const idx = secs.findIndex((s) => s.id === secId);
    if (idx === -1) return;
    pushUndoSnapshot();
    secs.splice(idx, 1);
    ensureSelection();
  }

  async function saveGlobalSettings() {
    const res = await callMegaBuilderApi<{ globalSettings: GlobalSettings }>(
      state.boot.ajaxUrl,
      state.boot.csrfToken,
      'saveGlobalSettings',
      { globalSettings: state.globalSettings }
    );
    if (res.ok && res.data.globalSettings) {
      state.globalSettings = deepClone(res.data.globalSettings);
      notify('Global Design System settings saved.', 'success');
    } else {
      notify(res.message || 'Failed to save Global Settings.', 'error');
    }
  }

  async function saveStudioTheme() {
    const res = await callMegaBuilderApi<{ studioTheme: StudioTheme }>(
      state.boot.ajaxUrl,
      state.boot.csrfToken,
      'saveStudioTheme',
      { studioTheme: state.studioTheme }
    );
    if (res.ok && res.data.studioTheme) {
      state.studioTheme = deepClone(res.data.studioTheme);
      notify('Studio UI Theme saved.', 'success');
    } else {
      notify(res.message || 'Failed to save Studio Theme.', 'error');
    }
  }

  async function refreshMedia(folder = state.activeMediaFolder) {
    const res = await callMegaBuilderApi<{
      folders: string[];
      files: MediaAsset[];
    }>(state.boot.ajaxUrl, state.boot.csrfToken, 'listMedia', {
      folder,
      search: state.mediaSearch,
    });
    if (res.ok) {
      state.mediaFolders = res.data.folders || [];
      state.mediaFiles = res.data.files || [];
    }
  }

  async function createMediaFolder() {
    if (!state.newFolderName.trim()) return;
    const res = await callMegaBuilderApi<{
      folders: string[];
      files: MediaAsset[];
    }>(state.boot.ajaxUrl, state.boot.csrfToken, 'createMediaFolder', {
      folder: state.newFolderName.trim(),
    });
    if (res.ok) {
      state.mediaFolders = res.data.folders || state.mediaFolders;
      state.mediaFiles = res.data.files || state.mediaFiles;
      state.activeMediaFolder = state.newFolderName.trim().toLowerCase();
      state.newFolderName = '';
      notify('Media folder created.', 'success');
    } else {
      notify(res.message || 'Could not create folder.', 'error');
    }
  }

  async function uploadMediaContent(filename: string, content: string) {
    const res = await callMegaBuilderApi<{
      uploaded: MediaAsset | null;
      media: { folders: string[]; files: MediaAsset[] };
    }>(state.boot.ajaxUrl, state.boot.csrfToken, 'uploadMedia', {
      folder: state.activeMediaFolder,
      filename,
      content,
    });
    if (res.ok && res.data.media) {
      state.mediaFolders = res.data.media.folders || state.mediaFolders;
      state.mediaFiles = res.data.media.files || state.mediaFiles;
      notify(res.message || 'Media uploaded & sanitized.', 'success');
      return true;
    } else {
      notify(res.message || 'Upload rejected by security sanitizer.', 'error');
      return false;
    }
  }

  async function deleteMediaFile(relPath: string) {
    const res = await callMegaBuilderApi<{
      folders: string[];
      files: MediaAsset[];
    }>(state.boot.ajaxUrl, state.boot.csrfToken, 'deleteMedia', {
      rel_path: relPath,
      folder: state.activeMediaFolder,
    });
    if (res.ok) {
      state.mediaFolders = res.data.folders || state.mediaFolders;
      state.mediaFiles = res.data.files || [];
      notify('Media asset removed.', 'info');
    }
  }

  async function exportAllTemplatesJson() {
    const res = await callMegaBuilderApi<{ bundle: Record<string, any> }>(
      state.boot.ajaxUrl,
      state.boot.csrfToken,
      'exportTemplates'
    );
    if (res.ok && res.data.bundle) {
      const blob = new Blob([JSON.stringify(res.data.bundle, null, 2)], {
        type: 'application/json',
      });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `megabuilder-backup-${new Date().toISOString().slice(0, 10)}.json`;
      a.click();
      URL.revokeObjectURL(url);
      notify('Exported complete JSON backup bundle.', 'success');
    }
  }

  ensureSelection();

  // If boot requested a specific initial template or section, load it
  if (boot.initialTemplate > 0) {
    openTemplateInEditor(boot.initialTemplate);
  } else if (boot.initialSection && boot.pageSections[boot.initialSection]) {
    const match = boot.templates.find(
      (t) => t.section_type === boot.initialSection
    );
    if (match) {
      openTemplateInEditor(match.id_template);
    } else {
      startFromBlueprint(boot.initialSection, 'page_template');
    }
  }

  return {
    state,
    filteredDashboardTemplates,
    subStudioTemplates,
    allSubComponents,
    filteredWidgets,
    selectedSection,
    selectedWidget,
    notify,
    pushUndoSnapshot,
    undo,
    redo,
    openTemplateInEditor,
    startFromBlueprint,
    createTemplateFromModal,
    saveCurrentTemplate,
    toggleTemplateStatus,
    duplicateTemplate,
    deleteTemplate,
    restoreRevision,
    openLivePreview,
    addSection,
    addColumnToSelectedSection,
    insertWidget,
    insertSavedTemplateIntoCanvas,
    moveWidget,
    duplicateWidget,
    removeWidget,
    moveSection,
    removeSection,
    saveGlobalSettings,
    saveStudioTheme,
    refreshMedia,
    createMediaFolder,
    uploadMediaContent,
    deleteMediaFile,
    exportAllTemplatesJson,
  };
}
