import type { InjectionKey, MaybeRefOrGetter, Ref } from 'vue';

/** A collapsible section or repeater row, as the content editor knows it. */
type Collapsible = {
    key: () => string;
    open: Ref<boolean>;
    hasError: () => boolean;
};

/**
 * Which content sections and repeater rows are open. It's kept by the top content editor, so it
 * lasts as long as the editor does, including through a failed save. Sections are keyed by
 * field slug (e.g. `content.features`) and rows by their `_key`, so reordering keeps them.
 */
type ContentState = {
    /** Whether each one is open, by key, once the user has opened or closed it. */
    openByKey: Map<string, boolean>;
    collapsibles: Set<Collapsible>;
    /** Bumped after a failed save, so the ones with errors open. */
    errorReveals: Ref<number>;
};

export const contentStateKey: InjectionKey<ContentState> =
    Symbol('cmsContentState');

function createContentState(): ContentState {
    return {
        openByKey: reactive(new Map<string, boolean>()),
        collapsibles: new Set(),
        errorReveals: ref(0),
    };
}

/** Keeps the open state for this editor and everything nested in it. */
export function provideContentState(): ContentState {
    const state = createContentState();

    provide(contentStateKey, state);

    return state;
}

export function useContentState(): ContentState {
    return inject(contentStateKey, createContentState, true);
}

/**
 * Opens or closes everything under the prefix, e.g. a repeater's rows (but not the repeater's own
 * section), or everything without one, for Expand all and Collapse all.
 */
export function setAllOpen(
    state: ContentState,
    open: boolean,
    prefix?: string,
): void {
    for (const collapsible of state.collapsibles) {
        if (!prefix || collapsible.key().startsWith(`${prefix}.`)) {
            collapsible.open.value = open;
        }
    }
}

/** Opens one section or row by key, e.g. a row that's just been added. */
export function openCollapsible(state: ContentState, key: string): void {
    state.openByKey.set(key, true);
}

/** Opens the sections and rows with errors, after a failed save. */
export function revealErrors(state: ContentState): void {
    state.errorReveals.value++;
}

/**
 * Whether a section or repeater row is open. It remembers the choice by key, opens when a failed
 * save leaves errors in it, and joins in with Expand all and Collapse all.
 */
export function useCollapsible(
    key: MaybeRefOrGetter<string>,
    options: { openByDefault: boolean; hasError?: () => boolean },
) {
    const state = useContentState();

    const open = computed({
        get: () => state.openByKey.get(toValue(key)) ?? options.openByDefault,
        set: (value: boolean) => {
            state.openByKey.set(toValue(key), value);
        },
    });

    const collapsible: Collapsible = {
        key: () => toValue(key),
        open,
        hasError: options.hasError ?? (() => false),
    };

    state.collapsibles.add(collapsible);
    onUnmounted(() => state.collapsibles.delete(collapsible));

    watch(state.errorReveals, () => {
        if (collapsible.hasError()) {
            open.value = true;
        }
    });

    function toggle() {
        open.value = !open.value;
    }

    return { open, toggle };
}
