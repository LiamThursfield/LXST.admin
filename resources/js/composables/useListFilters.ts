import type { MaybeRefOrGetter } from 'vue';

/** A filter value: text or a chosen option (blank when off), a tick box, or several chosen options. */
type FilterValue = string | boolean | string[];

/** How an active filter reads as a chip, e.g. "Status: Published", or just "Hidden" for a tick box. */
export type ListFilterLabel = {
    label: string;
    /** The options, to show the chosen one's label rather than its value. */
    items?: MaybeRefOrGetter<Array<{ value: unknown; label?: string }>>;
    /** The whole chip text, when "Label: value" doesn't read well. */
    format?: (value: string | string[]) => string;
};

/** An active filter, shown as a chip that removes it. */
export type ListFilterChip = {
    key: string;
    label: string;
};

/**
 * The filters (and search) of a list page, kept in the URL as `filter[...]`. Changing one reloads
 * the list from the first page, after a pause for typing. Blank text, unticked boxes and empty
 * choices are left out, and ticked boxes are sent as `1`. `extra` adds other query values, e.g.
 * the sort.
 */
export function useListFilters<T extends Record<string, FilterValue>>(options: {
    url: () => string;
    /** The filters as the page was loaded with them. */
    initial: T;
    /** Each filter's chip label; filters without one (e.g. search) get no chip. */
    labels?: Partial<Record<keyof T, ListFilterLabel>>;
    /** Values that mean a filter is off when it isn't blank, e.g. an "Any" option's `any`. */
    blanks?: Partial<T>;
    extra?: () => Record<string, unknown>;
}) {
    const filters = ref({ ...options.initial }) as Ref<T>;

    /** The value that turns a filter off. */
    function blankOf(key: string): FilterValue {
        const blank = options.blanks?.[key as keyof T];

        if (blank !== undefined) {
            return blank as FilterValue;
        }

        const current = filters.value[key];

        return typeof current === 'boolean'
            ? false
            : Array.isArray(current)
              ? []
              : '';
    }

    function isOff(key: string, value: FilterValue): boolean {
        const blank = blankOf(key);

        return Array.isArray(value)
            ? value.length === 0
            : value === blank || value === '' || value === false;
    }

    /** The filters as they're sent: only those set, ticked boxes as `1`. */
    function activeFilters(): Record<string, string | string[]> {
        return Object.fromEntries(
            Object.entries(filters.value)
                .filter(([key, value]) => !isOff(key, value))
                .map(([key, value]) => [key, value === true ? '1' : value]),
        ) as Record<string, string | string[]>;
    }

    function load(page: number) {
        router.get(
            options.url(),
            { page, filter: activeFilters(), ...(options.extra?.() ?? {}) },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    const reload = useDebounceFn(() => load(1), 300);

    watch(filters, () => reload(), { deep: true });

    if (options.extra) {
        watch(options.extra, () => reload(), { deep: true });
    }

    const hasFilters = computed(() => Object.keys(activeFilters()).length > 0);

    const chips = computed<ListFilterChip[]>(() =>
        Object.entries(activeFilters()).flatMap(([key, value]) => {
            const label = options.labels?.[key as keyof T];

            if (!label) {
                return [];
            }

            if (label.format) {
                return [{ key, label: label.format(value) }];
            }

            if (filters.value[key] === true) {
                return [{ key, label: label.label }];
            }

            const items = toValue(label.items ?? []);
            const chosen = (Array.isArray(value) ? value : [value]).map(
                (one) =>
                    items.find((option) => String(option.value) === one)
                        ?.label ?? one,
            );

            return [{ key, label: `${label.label}: ${chosen.join(', ')}` }];
        }),
    );

    /** Turns one filter off, e.g. from its chip. */
    function remove(key: string) {
        (filters.value as Record<string, FilterValue>)[key] = blankOf(key);
    }

    /** Turns every filter off, search included. */
    function clear() {
        for (const key of Object.keys(filters.value)) {
            remove(key);
        }
    }

    return { filters, hasFilters, chips, remove, clear, goToPage: load };
}
