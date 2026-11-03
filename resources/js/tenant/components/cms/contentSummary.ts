import type {
    ContentValues,
    SavedComponentOption,
    TemplateData,
    TemplateFieldData,
} from '@/types';
import { resolveSavedValues } from './nestedTemplates';

/** The field types a row's title and subtitle, and a collapsed summary, can come from. */
const textTypes = ['text', 'textarea'];

/** The longest opening shown for rich text. */
const openingLength = 80;

export function isTextField(field: TemplateFieldData): boolean {
    return textTypes.includes(field.type);
}

/**
 * The fields and their values, with each component swapped for the fields inside it, so a row made
 * up of a component can be described by what's in that component. A component using a saved
 * component is described by that saved component's content.
 */
export function withComponentFields(
    fields: TemplateFieldData[],
    values: ContentValues | null | undefined,
    templates: TemplateData[],
    savedComponents: SavedComponentOption[] = [],
    visited: number[] = [],
): Array<{ field: TemplateFieldData; value: unknown }> {
    const resolved = resolveSavedValues(values, savedComponents);

    return fields.flatMap((field) => {
        const value = resolved?.[field.slug];

        if (field.type !== 'component') {
            return [{ field, value }];
        }

        const id = field.settings.template_id;
        const template = templates.find((item) => item.id === id);

        return template && !visited.includes(template.id)
            ? withComponentFields(
                  template.fields ?? [],
                  value,
                  templates,
                  savedComponents,
                  [...visited, template.id],
              )
            : [];
    });
}

/** The text fields' values (and those inside components), in field order, skipping empty ones. */
export function textValues(
    fields: TemplateFieldData[],
    values: ContentValues | null | undefined,
    templates: TemplateData[] = [],
    savedComponents: SavedComponentOption[] = [],
): string[] {
    return withComponentFields(fields, values, templates, savedComponents)
        .filter(({ field }) => isTextField(field))
        .map(({ value }) => value)
        .filter(
            (value): value is string =>
                typeof value === 'string' && value.trim() !== '',
        )
        .map((value) => value.trim());
}

/** The words in a rich text value, from its HTML. */
export function richTextWords(
    value: { html?: string | null } | null | undefined,
): string[] {
    if (!value?.html) {
        return [];
    }

    const text =
        new DOMParser().parseFromString(value.html, 'text/html').body
            .textContent ?? '';

    return text.split(/\s+/).filter(Boolean);
}

/** The opening words of some text, cut at a word to fit the summary. */
export function opening(words: string[]): string {
    const text = words.join(' ');

    if (text.length <= openingLength) {
        return text;
    }

    return `${text.slice(0, openingLength).replace(/\s+\S*$/, '')}…`;
}
