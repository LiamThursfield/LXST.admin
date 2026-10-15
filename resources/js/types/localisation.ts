/**
 * The translation groups shared from lang/{locale}/*.php, keyed by group (file) name.
 * e.g. { common: { actions: { save: 'Save' } } }
 */
export type Translations = {
    [key: string]: string | Translations;
};

/**
 * Values that replace :placeholders in a translation, as Laravel's __() does.
 */
export type TranslationReplacements = Record<string, string | number>;
