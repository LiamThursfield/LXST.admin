import type { TranslationReplacements, Translations } from '@/types';

export type UseTransReturn = {
    trans: (key: string, replacements?: TranslationReplacements) => string;
    transChoice: (
        key: string,
        count: number,
        replacements?: TranslationReplacements,
    ) => string;
};

/**
 * Finds the translation for a dot notation key, e.g. 'common.actions.save'.
 */
function findTranslation(
    translations: Translations | undefined,
    key: string,
): string | null {
    let value: string | Translations | undefined = translations;

    for (const segment of key.split('.')) {
        if (value == null || typeof value === 'string') {
            return null;
        }

        value = value[segment];
    }

    return typeof value === 'string' ? value : null;
}

/**
 * Replaces :placeholder, :Placeholder and :PLACEHOLDER as Laravel's __() does.
 * Longer placeholders are replaced first so :name does not clash with :name_full.
 */
export function replacePlaceholders(
    line: string,
    replacements: TranslationReplacements = {},
): string {
    return Object.keys(replacements)
        .sort((a, b) => b.length - a.length)
        .reduce((result, placeholder) => {
            const value = String(replacements[placeholder]);
            const capitalised = value.charAt(0).toUpperCase() + value.slice(1);
            const capitalisedPlaceholder =
                placeholder.charAt(0).toUpperCase() + placeholder.slice(1);

            return result
                .replaceAll(
                    `:${placeholder.toUpperCase()}`,
                    value.toUpperCase(),
                )
                .replaceAll(`:${capitalisedPlaceholder}`, capitalised)
                .replaceAll(`:${placeholder}`, value);
        }, line);
}

/**
 * Returns the segment for an explicit range such as {0}, [1,19] or [20,*], or null if none match.
 */
function findExplicitSegment(segments: string[], count: number): string | null {
    for (const segment of segments) {
        const match = segment.match(/^[{[]([^\]}]*)[\]}]\s*(.*)$/s);
        if (!match) {
            continue;
        }

        const [, condition, line] = match;

        if (condition.includes(',')) {
            const [from, to] = condition.split(',').map((part) => part.trim());
            const isAboveFrom = from === '*' || count >= Number(from);
            const isBelowTo = to === '*' || count <= Number(to);

            if (isAboveFrom && isBelowTo) {
                return line;
            }
        } else if (Number(condition) === count) {
            return line;
        }
    }

    return null;
}

/**
 * Picks the plural form for the count, supporting Laravel's 'one|many' and
 * explicit range ('{0} None|[1,*] Some') formats.
 */
export function choosePluralForm(line: string, count: number): string {
    const segments = line.split('|');

    const explicit = findExplicitSegment(segments, count);
    if (explicit !== null) {
        return explicit;
    }

    const plainSegments = segments.map((segment) =>
        segment.replace(/^[{[]([^\]}]*)[\]}]\s*/s, ''),
    );

    if (plainSegments.length === 1) {
        return plainSegments[0];
    }

    return count === 1 ? plainSegments[0] : plainSegments[1];
}

/**
 * Translates keys from the lang/{locale}/*.php files shared by HandleInertiaRequests.
 * Missing keys return the key itself, matching Laravel's __() behaviour.
 */
export function useTrans(): UseTransReturn {
    const page = usePage();

    function trans(
        key: string,
        replacements: TranslationReplacements = {},
    ): string {
        const line = findTranslation(page.props.translations, key);

        return line === null ? key : replacePlaceholders(line, replacements);
    }

    function transChoice(
        key: string,
        count: number,
        replacements: TranslationReplacements = {},
    ): string {
        const line = findTranslation(page.props.translations, key);

        if (line === null) {
            return key;
        }

        return replacePlaceholders(choosePluralForm(line, count), {
            count,
            ...replacements,
        });
    }

    return { trans, transChoice };
}
