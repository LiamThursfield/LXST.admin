/** A tag to choose or show on items. */
export interface CatalogueTagOption {
    value: number;
    label: string;
    icon: string | null;
    kind: 'allergen' | 'dietary' | 'other';
}

export interface CatalogueTagData {
    id: number;
    preset: string | null;
    kind: 'allergen' | 'dietary' | 'other';
    kind_label: string;
    name: string;
    icon: string | null;
    order: number;
    items_count: number | null;
    actions?: any[];
}

/** A group of built-in tags, such as the allergens. */
export interface CatalogueTagPresetGroup {
    key: string;
    label: string;
    kind: string;
    suggested: boolean;
    /** How many of its tags the tenant doesn't have yet. */
    missing: number;
}

export interface CatalogueVariationData {
    id: number | null;
    name: string;
    /** In major units, e.g. "6.50". */
    price: string;
    /** In minor units. Only on saved variations. */
    price_minor?: number;
    stock: number | null;
}

export interface CatalogueItemData {
    id: number | null;
    name: string;
    description: string | null;
    service_id: number | null;
    service_name?: string | null;
    image_file_id: number | null;
    image_url?: string | null;
    /** In major units, e.g. "6.50". Null for no price of its own. */
    price: string | null;
    /** In minor units, from the service or variations when it has them. */
    display_price?: { amount: number | null; from: boolean };
    is_available: boolean;
    tracks_stock: boolean;
    stock: number | null;
    in_stock?: boolean;
    tag_ids: number[];
    variations: CatalogueVariationData[];
}

export interface CatalogueSectionData {
    id: number | null;
    name: string;
    description: string | null;
    items: CatalogueItemData[];
}

export interface CatalogueData {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    is_published: boolean;
    /** ISO days, 1 being Monday. Empty is every day. */
    available_days: number[];
    available_from: string | null;
    available_until: string | null;
    starts_on: string | null;
    ends_on: string | null;
    /** When it applies, in words, or null when always. */
    schedule: string | null;
    applies_now: boolean;
    sections_count: number | null;
    items_count: number | null;
    /** E.g. "3 sections · 8 items". Only in the list. */
    contents: string | null;
    url: string;
    sections?: CatalogueSectionData[];
    actions?: any[];
}

/** A bookable service an item can show. */
export interface CatalogueServiceOption {
    value: number;
    label: string;
    /** In minor units, or null when priced on quote. */
    price: number | null;
    from: boolean;
}
