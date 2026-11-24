import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createStudioStore } from '../../ui/useStudioStore';
import { makeBoot } from './fixtures';

function widgetsIn(store: ReturnType<typeof createStudioStore>) {
  return store.state.currentTemplate.layout.sections.flatMap((s) => s.columns.flatMap((c) => c.widgets));
}

describe('studio canvas editing', () => {
  let store: ReturnType<typeof createStudioStore>;

  beforeEach(() => {
    store = createStudioStore(makeBoot());
    store.state.selectedColumnId = 'col_a1';
  });

  it('starts from the homepage blueprint with no undo history', () => {
    expect(store.state.currentTemplate.layout.sections).toHaveLength(1);
    expect(store.state.undoStack).toHaveLength(0);
    expect(store.state.isDirty).toBe(false);
  });

  it('inserts a widget with its defaults into the selected column', () => {
    store.insertWidget('heading');
    const [w] = widgetsIn(store);
    expect(w.type).toBe('heading');
    expect(w.content).toMatchObject({ text: 'Hello', subtitle: 'EYEBROW' });
    expect(w.style.accent_color).toBe('#123456');
    expect(store.state.selectedWidgetId).toBe(w.id);
    expect(store.state.isDirty).toBe(true);
  });

  it('does not share default objects between inserted widgets', () => {
    store.insertWidget('heading');
    store.insertWidget('heading');
    const [a, b] = widgetsIn(store);
    a.content.text = 'changed';
    expect(b.content.text).toBe('Hello');
    expect(a.id).not.toBe(b.id);
  });

  it('creates a section when the canvas is empty', () => {
    store.state.currentTemplate.layout.sections = [];
    store.insertWidget('button');
    expect(store.state.currentTemplate.layout.sections).toHaveLength(1);
    expect(widgetsIn(store)[0].type).toBe('button');
  });

  it('undoes and redoes widget insertion', () => {
    store.insertWidget('heading');
    store.insertWidget('button');
    expect(widgetsIn(store).map((w) => w.type)).toEqual(['heading', 'button']);

    store.undo();
    expect(widgetsIn(store).map((w) => w.type)).toEqual(['heading']);
    store.undo();
    expect(widgetsIn(store)).toHaveLength(0);

    store.redo();
    store.redo();
    expect(widgetsIn(store).map((w) => w.type)).toEqual(['heading', 'button']);
  });

  it('clears redo history after a new change', () => {
    store.insertWidget('heading');
    store.undo();
    expect(store.state.redoStack).toHaveLength(1);
    store.insertWidget('button');
    expect(store.state.redoStack).toHaveLength(0);
  });

  it('caps undo history at 50 steps', () => {
    for (let i = 0; i < 60; i++) store.insertWidget('heading');
    expect(store.state.undoStack.length).toBe(50);
  });

  it('moves, duplicates and removes widgets', () => {
    store.insertWidget('heading');
    store.insertWidget('button');
    const [h, b] = widgetsIn(store);

    store.moveWidget(b.id, -1);
    expect(widgetsIn(store).map((w) => w.id)).toEqual([b.id, h.id]);
    store.moveWidget(b.id, -1); // already first: no-op
    expect(widgetsIn(store).map((w) => w.id)).toEqual([b.id, h.id]);

    store.duplicateWidget(h.id);
    const after = widgetsIn(store);
    expect(after).toHaveLength(3);
    expect(after[2].label).toBe('Heading (Copy)');
    expect(after[2].id).not.toBe(h.id);

    store.removeWidget(b.id);
    expect(widgetsIn(store).some((w) => w.id === b.id)).toBe(false);
  });

  it('adds sections with evenly split columns and reorders them', () => {
    store.addSection(3);
    const secs = store.state.currentTemplate.layout.sections;
    expect(secs).toHaveLength(2);
    expect(secs[1].columns.map((c) => c.width)).toEqual([33.33, 33.33, 33.33]);
    expect(secs[1].columns.every((c) => c.width_mobile === 100)).toBe(true);

    const newId = secs[1].id;
    store.moveSection(newId, -1);
    expect(store.state.currentTemplate.layout.sections[0].id).toBe(newId);
    store.removeSection(newId);
    expect(store.state.currentTemplate.layout.sections).toHaveLength(1);
  });

  it('never adds more than 6 columns to a section', () => {
    store.state.selectedSectionId = 'sec_a';
    for (let i = 0; i < 10; i++) store.addColumnToSelectedSection();
    expect(store.state.currentTemplate.layout.sections[0].columns).toHaveLength(6);
  });

  it('refuses to insert a coming-soon widget and says why', () => {
    store.insertWidget('breadcrumb');
    expect(widgetsIn(store)).toHaveLength(0);
    expect(store.state.undoStack).toHaveLength(0);
    expect(store.state.toastMessage).toContain('coming soon');
  });

  it('lists ready widgets before coming-soon ones', () => {
    const types = store.filteredWidgets.value.map((w) => w.type);
    expect(types[types.length - 1]).toBe('breadcrumb');
    expect(store.comingSoonTypes.value.has('breadcrumb')).toBe(true);
    expect(store.comingSoonTypes.value.has('heading')).toBe(false);
  });

  it('inserts a saved template as a linked shortcode widget', () => {
    store.insertSavedTemplateIntoCanvas({ id_template: 12, section_type: 'slider', title: 'Hero slider' } as never, 'shortcode');
    const [w] = widgetsIn(store);
    expect(w.type).toBe('template_widget');
    expect(w.content.template_id).toBe(12);
  });
});

describe('studio API calls', () => {
  const fetchMock = vi.fn();

  beforeEach(() => {
    fetchMock.mockReset();
    vi.stubGlobal('fetch', fetchMock);
  });
  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it('saves the template with the CSRF header and updates the record from the response', async () => {
    fetchMock.mockResolvedValue({
      json: async () => ({
        ok: true,
        code: 'TEMPLATE_SAVED',
        message: 'saved',
        data: { template: { id_template: 99, revisions: [{ id_revision: 1 }] }, templates: [{ id_template: 99 }] },
        errors: [],
      }),
    });
    const store = createStudioStore(makeBoot());
    store.insertWidget('heading');

    await store.saveCurrentTemplate(1);

    expect(fetchMock).toHaveBeenCalledTimes(1);
    const [url, init] = fetchMock.mock.calls[0];
    expect(url).toContain('mb_action=saveTemplate');
    expect(init.method).toBe('POST');
    expect(init.headers['X-MB-CSRF-Token']).toBe('c'.repeat(64));
    const body = JSON.parse(init.body);
    expect(body.action).toBe('saveTemplate');
    expect(body.active).toBe(1);
    expect(body.revision_label).toBe('Published Checkpoint');
    expect(body.layout.sections[0].columns[0].widgets[0].type).toBe('heading');

    expect(store.state.currentTemplate.id_template).toBe(99);
    expect(store.state.isDirty).toBe(false);
    expect(store.state.isSaving).toBe(false);
    expect(store.state.boot.templates).toHaveLength(1);
  });

  it('keeps unsaved changes and reports the error when saving fails', async () => {
    fetchMock.mockResolvedValue({
      json: async () => ({ ok: false, code: 'CSRF_INVALID', message: 'Invalid token', data: {}, errors: [] }),
    });
    const store = createStudioStore(makeBoot());
    store.insertWidget('heading');

    await store.saveCurrentTemplate();

    expect(store.state.isDirty).toBe(true);
    expect(store.state.toastType).toBe('error');
    expect(store.state.toastMessage).toBe('Invalid token');
  });

  it('turns a network failure into an error envelope instead of throwing', async () => {
    fetchMock.mockRejectedValue(new Error('offline'));
    const store = createStudioStore(makeBoot());

    await expect(store.saveCurrentTemplate()).resolves.toBeUndefined();
    expect(store.state.toastType).toBe('error');
    expect(store.state.isSaving).toBe(false);
  });
});
