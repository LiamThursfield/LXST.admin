export type Appearance = 'light' | 'dark' | 'system';
export type ResolvedAppearance = 'light' | 'dark';

export type AppShellVariant = 'header' | 'sidebar';

/** A single option for a select-style input (Nuxt UI `USelect`, etc.). */
export type SelectItem = { label: string; value: string | number };

/** What a site needs to render the tenant's captcha (#74), from the public API. */
export type CaptchaWidgetData = {
    provider: 'turnstile';
    site_key: string;
    /** The field to send the widget's token in. */
    token_field: string;
};
