import type { InjectionKey, Ref, ShallowRef } from 'vue';
import type {
    ContentValues,
    TemplateData,
    TemplateFieldData,
    TemplateSectionData,
} from '@/types';

/**
 * One item open in the content panel: a row of a list, or a component. It points at where the item
 * lives rather than holding a copy, so edits go straight into the form and the panel never saves.
 */
export type PanelLevel = {
    /** The list or component field the item belongs to. */
    field: TemplateFieldData;
    /** The values holding that field. */
    parent: ContentValues;
    /** Where the field's errors are, e.g. `content.slides`. */
    prefix: string;
    /** Where open rows inside it are remembered, e.g. `content.slides`. */
    stateKey: string;
    /** The row's place in the list, or null for a component. */
    index: Ref<number> | null;
};

/** What a level's editor shows. */
export type PanelContents = {
    fields: TemplateFieldData[];
    sections: TemplateSectionData[];
    values: ContentValues;
    prefix: string;
    stateKey: string;
};

/** The stack of open items, kept by the top content editor. Nothing open means the panel is closed. */
type ContentPanel = {
    levels: ShallowRef<PanelLevel[]>;
    push: (level: PanelLevel) => void;
    closeTo: (count: number) => void;
};

export const contentPanelKey: InjectionKey<ContentPanel> =
    Symbol('cmsContentPanel');

/** Provided by the panel, so what's inside it opens one level deeper in the panel too. */
export const insideContentPanelKey: InjectionKey<boolean> = Symbol(
    'cmsInsideContentPanel',
);

export function provideContentPanel(): ContentPanel {
    const levels = shallowRef<PanelLevel[]>([]);

    const panel: ContentPanel = {
        levels,
        push: (level) => {
            levels.value = [...levels.value, level];
        },
        closeTo: (count) => {
            levels.value = levels.value.slice(0, count);
        },
    };

    provide(contentPanelKey, panel);

    return panel;
}

export function useContentPanel(): ContentPanel | null {
    return inject(contentPanelKey, null);
}

export function useInsideContentPanel(): boolean {
    return inject(insideContentPanelKey, false);
}

/**
 * Only one level of nesting is drawn inline: an item holding further components or lists opens in
 * the panel instead, as does everything inside the panel. Fields at the top of the editor are
 * already in a card of their own, so a component there never opens in the panel.
 */
export function opensInPanel(
    fields: TemplateFieldData[],
    insidePanel: boolean,
): boolean {
    return (
        insidePanel ||
        fields.some((field) => ['component', 'repeater'].includes(field.type))
    );
}

function nestedTemplateFor(
    field: TemplateFieldData,
    templates: TemplateData[],
): TemplateData | undefined {
    return templates.find((item) => item.id === field.settings.template_id);
}

/**
 * Gives every component and list its empty value and every row its `_key`, as their editors do when
 * they mount. Items in the panel don't mount until they're opened, and opening one mustn't count as
 * a change.
 */
export function fillNestedValues(
    fields: TemplateFieldData[],
    values: ContentValues,
    templates: TemplateData[],
    visited: number[] = [],
): void {
    for (const field of fields) {
        const template = nestedTemplateFor(field, templates);

        if (!template || visited.includes(template.id)) {
            continue;
        }

        const path = [...visited, template.id];

        if (field.type === 'component') {
            if (
                values[field.slug] === null ||
                typeof values[field.slug] !== 'object'
            ) {
                values[field.slug] = {};
            }

            fillNestedValues(
                template.fields ?? [],
                values[field.slug],
                templates,
                path,
            );
        }

        if (field.type === 'repeater') {
            if (!Array.isArray(values[field.slug])) {
                values[field.slug] = [];
            }

            const rows: ContentValues[] = values[field.slug];
            let nextKey =
                Math.max(-1, ...rows.map((row) => row._key ?? -1)) + 1;

            for (const row of rows) {
                row._key ??= nextKey++;
                fillNestedValues(template.fields ?? [], row, templates, path);
            }
        }
    }
}

/** A row of a list, with what's inside it ready to edit. */
export function rowLevel(
    field: TemplateFieldData,
    parent: ContentValues,
    prefix: string,
    stateKey: string,
    index: number,
    templates: TemplateData[],
): PanelLevel {
    fillNestedValues(
        nestedTemplateFor(field, templates)?.fields ?? [],
        parent[field.slug][index],
        templates,
    );

    return { field, parent, prefix, stateKey, index: ref(index) };
}

/** A component, with what's inside it ready to edit. */
export function componentLevel(
    field: TemplateFieldData,
    parent: ContentValues,
    prefix: string,
    stateKey: string,
    templates: TemplateData[],
): PanelLevel {
    fillNestedValues([field], parent, templates);

    return { field, parent, prefix, stateKey, index: null };
}

/**
 * What a level shows. A row that's just one component shows that component's fields, so a list of
 * cards doesn't need a second click to reach each card. Null if the item has gone.
 */
export function levelContents(
    level: PanelLevel,
    templates: TemplateData[],
): PanelContents | null {
    const template = nestedTemplateFor(level.field, templates);
    const value = level.parent[level.field.slug];

    if (!template) {
        return null;
    }

    const contents = {
        fields: template.fields ?? [],
        sections: template.sections ?? [],
    };

    if (level.index === null) {
        return value
            ? {
                  ...contents,
                  values: value,
                  prefix: level.prefix,
                  stateKey: level.stateKey,
              }
            : null;
    }

    const row: ContentValues | undefined = value?.[level.index.value];

    if (!row) {
        return null;
    }

    const prefix = `${level.prefix}.${level.index.value}`;
    const stateKey = `${level.stateKey}.${row._key}`;
    const [only] = contents.fields;
    const inner =
        contents.fields.length === 1 && only.type === 'component'
            ? nestedTemplateFor(only, templates)
            : undefined;

    return inner && row[only.slug]
        ? {
              fields: inner.fields ?? [],
              sections: inner.sections ?? [],
              values: row[only.slug],
              prefix: `${prefix}.${only.slug}`,
              stateKey: `${stateKey}.${only.slug}`,
          }
        : { ...contents, values: row, prefix, stateKey };
}

/**
 * The items to open to reach an error, e.g. `content.cards.1.card.prices.0.title`, following the
 * same rules as clicking through to it. Empty if it can be seen without the panel.
 */
export function panelLevelsForError(
    errorKey: string,
    fields: TemplateFieldData[],
    values: ContentValues,
    prefix: string,
    stateKey: string,
    templates: TemplateData[],
    insidePanel = false,
    isTopLevel = true,
): PanelLevel[] {
    const path = errorKey.slice(prefix.length + 1).split('.');
    const field = fields.find((item) => item.slug === path[0]);
    const template = field && nestedTemplateFor(field, templates);

    if (!field || !template) {
        return [];
    }

    const fieldPrefix = `${prefix}.${field.slug}`;
    const fieldStateKey = `${stateKey}.${field.slug}`;
    const nestedFields = template.fields ?? [];
    const walkInline = (
        inlineValues: ContentValues,
        inlinePrefix: string,
        inlineStateKey: string,
    ) =>
        panelLevelsForError(
            errorKey,
            nestedFields,
            inlineValues,
            inlinePrefix,
            inlineStateKey,
            templates,
            insidePanel,
            false,
        );

    let level: PanelLevel;

    if (field.type === 'component') {
        const value = values[field.slug];

        if (!value) {
            return [];
        }

        if (isTopLevel || !opensInPanel(nestedFields, insidePanel)) {
            return walkInline(value, fieldPrefix, fieldStateKey);
        }

        level = componentLevel(
            field,
            values,
            fieldPrefix,
            fieldStateKey,
            templates,
        );
    } else {
        const index = Number(path[1]);
        const row = values[field.slug]?.[index];

        if (!row) {
            return [];
        }

        if (!opensInPanel(nestedFields, insidePanel)) {
            return walkInline(
                row,
                `${fieldPrefix}.${index}`,
                `${fieldStateKey}.${row._key}`,
            );
        }

        level = rowLevel(
            field,
            values,
            fieldPrefix,
            fieldStateKey,
            index,
            templates,
        );
    }

    const contents = levelContents(level, templates);

    return [
        level,
        ...(contents
            ? panelLevelsForError(
                  errorKey,
                  contents.fields,
                  contents.values,
                  contents.prefix,
                  contents.stateKey,
                  templates,
                  true,
                  false,
              )
            : []),
    ];
}
