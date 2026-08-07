export type Appearance = 'light' | 'dark' | 'system';
export type ResolvedAppearance = 'light' | 'dark';

export type AppShellVariant = 'header' | 'sidebar';

/** A single option for a select-style input (Nuxt UI `USelect`, etc.). */
export type SelectItem = { label: string; value: string | number };
