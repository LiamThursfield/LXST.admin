/** A redirect's status (see backend `RedirectStatus`). */
export type RedirectStatus = 'active' | 'scheduled' | 'expired' | 'disabled';

/** A redirect, as returned by `RedirectResource`. */
export type RedirectData = {
    id: number;
    url_from: string;
    /** A path on the site, or a full URL when `is_external`. */
    url_to: string;
    is_external: boolean;
    is_enabled: boolean;
    is_permanent: boolean;
    http_status: 301 | 302;
    status: RedirectStatus;
    status_label: string;
    published_at: string | null;
    expired_at: string | null;
    /** The dates on the user's clock, for datetime-local inputs. */
    published_at_local: string | null;
    expired_at_local: string | null;
    actions?: any[];
};

export type TemplateType = 'page' | 'layout' | 'component' | 'repeater';

export type TemplateFieldSettingKey =
    'max_length' | 'rows' | 'min' | 'max' | 'template_id';

/** A field type the template builder can offer (see `TemplateController::builderOptions()`). */
export type TemplateFieldTypeOption = {
    value: string;
    label: string;
    settingKeys: TemplateFieldSettingKey[];
    /** The template types that allow this field type. */
    templateTypes: TemplateType[];
    /** False until the type's content editor exists, or when it needs a module the tenant doesn't have. */
    isAvailable: boolean;
};

export type TemplateFieldData = {
    id: number | null;
    type: string;
    type_label?: string;
    name: string;
    slug: string;
    description: string | null;
    is_required: boolean;
    settings: Partial<Record<TemplateFieldSettingKey, number | null>>;
};

/** A template, as returned by `TemplateResource`. */
export type TemplateData = {
    id: number;
    type: TemplateType;
    type_label: string;
    name: string;
    slug: string;
    description: string | null;
    fields_count?: number;
    fields?: TemplateFieldData[];
    actions?: any[];
};

/** Content for a template's fields, keyed by field slug. */
export type ContentValues = Record<string, unknown>;

/** A layout, as returned by `LayoutResource`. */
export type LayoutData = {
    id: number;
    template_id: number;
    name: string;
    slug: string;
    template?: { id: number; name: string };
    content?: ContentValues;
    actions?: any[];
};
