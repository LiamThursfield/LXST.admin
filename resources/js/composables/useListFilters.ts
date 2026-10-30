import type { MaybeRefOrGetter } from 'vue';

/** A filter value: text or a chosen option (blank when off), or a tick box. */
type FilterValue = string | boolean;

/** How an active filter reads as a chip, e.g. "Status: Published", or just "Hidden" for a tick box. */
export type ListFilterLabel = {
    label: string;
    /** The options, to show the chosen one's label rather than its value. */
    items?: MaybeRefOrGetter<Array<{ value: unknown; label?: string }>>;
};

/** An active filter, shown as a chip that removes it. */
export type ListFilterChip = {
    key: string;
    label: string;
};

/**
 * The filters (and search) of a list page, kept in the URL as `filter[...]`. Changing one reloads
 * the list from the first page, after a pause for typing. Blank text and unticked boxes are left
 * out, and ticked boxes are sent as `1`. `extra` adds other query values, e.g. the sort.
 */
export function useListFilters<T extends Record<string, FilterValue>>(options: {
    url: () => string;
    /** The filters as the page was loaded with them. */
    initial: T;
    /** Each filter's chip label; filters without one (e.g. search) get no chip. */
    labels?: Partial<Record<keyof T, ListFilterLabel>>;
    extra?: () => Record<string, unknown>;
}) {
    const filters = ref({ ...options.initial }) as Ref<T>;

    /** The filters as they're sent: only those set, ticked boxes as `1`. */
    function activeFilters(): Record<string, string> {
        return Object.fromEntries(
            Object.entries(filters.value)
                .filter(([, value]) => value !== '' && value !== false)
                .map(([key, value]) => [key, value === true ? '1' : value]),
        ) as Record<string, string>;
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

            if (filters.value[key] === true) {
                return [{ key, label: label.label }];
            }

            const item = toValue(label.items ?? []).find(
                (option) => String(option.value) === value,
            );

            return [{ key, label: `${label.label}: ${item?.label ?? value}` }];
        }),
    );

    /** Turns one filter off, e.g. from its chip. */
    function remove(key: string) {
        const current = filters.value[key];

        (filters.value as Record<string, FilterValue>)[key] =
            typeof current === 'boolean' ? false : '';
    }

    /** Turns every filter off, search included. */
    function clear() {
        for (const key of Object.keys(filters.value)) {
            remove(key);
        }
    }

    return { filters, hasFilters, chips, remove, clear, goToPage: load };
}
