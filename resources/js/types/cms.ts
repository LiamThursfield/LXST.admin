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
    | 'max_length'
    | 'rows'
    | 'min'
    | 'max'
    | 'template_id'
    | 'min_items'
    | 'max_items'
    | 'organisation_unit_type';

/** A component or repeater template a field can nest (see `TemplateController::builderOptions()`). */
export type NestedTemplateOption = {
    id: number;
    type: TemplateType;
    name: string;
};

/** A field type the template builder can offer (see `TemplateController::builderOptions()`). */
export type TemplateFieldTypeOption = {
    value: string;
    label: string;
    settingKeys: TemplateFieldSettingKey[];
    /** The template types that allow this field type. */
    templateTypes: TemplateType[];
    /** False until the type's content editor exists. */
    isAvailable: boolean;
    /** The type needs a module the tenant doesn't have (e.g. CRM), so it isn't offered. */
    needsModule: boolean;
};

export type TemplateFieldData = {
    id: number | null;
    type: string;
    type_label?: string;
    name: string;
    slug: string;
    description: string | null;
    is_required: boolean;
    settings: Partial<
        Record<
            Exclude<TemplateFieldSettingKey, 'organisation_unit_type'>,
            number | null
        >
    > & { organisation_unit_type?: string | null };
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
export type ContentValues = Record<string, any>;

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

export type PublishStatus = 'published' | 'scheduled' | 'expired' | 'draft';

export type PageMetadata = {
    title: string | null;
    description: string | null;
    keywords: string | null;
    canonical: string | null;
    og_title: string | null;
    og_description: string | null;
    og_type: string | null;
    og_url: string | null;
    og_image_file_id: number | null;
};

/** A page, as returned by `PageResource`. */
export type PageData = {
    id: number;
    template_id: number;
    layout_id: number;
    parent_id: number | null;
    name: string;
    slug: string;
    template?: { id: number; name: string };
    url: string | null;
    /** How far to indent the page under its parent in the tree. */
    depth: number;
    status: PublishStatus | null;
    status_label: string | null;
    is_enabled: boolean;
    published_at: string | null;
    expired_at: string | null;
    published_at_local: string | null;
    expired_at_local: string | null;
    children_count?: number;
    content?: ContentValues;
    metadata?: PageMetadata;
    actions?: any[];
};

/** A menu item (see backend `MenuItemData`), with `_key` added by the editor while dragging. */
export type MenuItem = {
    _key?: number;
    label: string;
    link_type: 'page' | 'url';
    page_id: number | null;
    url: string | null;
    target: '_self' | '_blank' | null;
    rel: string | null;
    custom_class: string | null;
    children: MenuItem[];
};

/** A menu, as returned by `MenuResource`. */
export type MenuData = {
    id: number;
    name: string;
    slug: string;
    items_count: number;
    menu_items: MenuItem[];
    actions?: any[];
};

/** A page a menu item can link to. */
export type MenuPageOption = {
    value: number;
    label: string;
    url: string | null;
};
