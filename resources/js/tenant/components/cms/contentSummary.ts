import type { ContentValues, TemplateFieldData } from '@/types';

/** The field types a row's title and subtitle, and a collapsed summary, can come from. */
const textTypes = ['text', 'textarea'];

/** The longest opening shown for rich text. */
const openingLength = 80;

export function isTextField(field: TemplateFieldData): boolean {
    return textTypes.includes(field.type);
}

/** The text fields' values, in field order, skipping empty ones. */
export function textValues(
    fields: TemplateFieldData[],
    values: ContentValues | null | undefined,
): string[] {
    return fields
        .filter(isTextField)
        .map((field) => values?.[field.slug])
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
