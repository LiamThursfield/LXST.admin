import type { PendingVisit } from '@inertiajs/core';
import type { MaybeRefOrGetter } from 'vue';

/**
 * Asks before leaving a form with unsaved changes, for as long as the calling component is
 * mounted. Closing or reloading the tab gets the browser's own prompt. Inertia visits away (GET
 * visits other than prefetches and partial reloads) are held until the user confirms with
 * `leave()`, or cancels with `stay()`. Saves and deletes aren't GET visits, so they aren't held.
 */
export function useUnsavedChanges(isDirty: MaybeRefOrGetter<boolean>) {
    const isConfirmOpen = ref(false);

    /** The visit held while the user decides, and whether it may now go ahead. */
    let heldVisit: PendingVisit | null = null;
    let isLeaving = false;

    function onBeforeUnload(event: BeforeUnloadEvent) {
        if (toValue(isDirty)) {
            event.preventDefault();
        }
    }

    window.addEventListener('beforeunload', onBeforeUnload);

    const removeBeforeListener = router.on('before', (event) => {
        const visit = event.detail.visit;

        if (
            isLeaving ||
            !toValue(isDirty) ||
            visit.method !== 'get' ||
            visit.prefetch ||
            visit.only.length > 0 ||
            visit.except.length > 0
        ) {
            return;
        }

        heldVisit = visit;
        isConfirmOpen.value = true;

        return false;
    });

    onUnmounted(() => {
        window.removeEventListener('beforeunload', onBeforeUnload);
        removeBeforeListener();
    });

    function leave() {
        isConfirmOpen.value = false;

        if (!heldVisit) {
            return;
        }

        isLeaving = true;
        router.visit(heldVisit.url.href, {
            preserveScroll: heldVisit.preserveScroll,
            preserveState: heldVisit.preserveState,
            replace: heldVisit.replace,
            onFinish: () => {
                isLeaving = false;
            },
        });
        heldVisit = null;
    }

    function stay() {
        isConfirmOpen.value = false;
        heldVisit = null;
    }

    return { isConfirmOpen, leave, stay };
}
