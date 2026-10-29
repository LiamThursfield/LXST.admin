/**
 * Whether some data has changed since it was first read, compared by value. For forms whose
 * fields live outside an Inertia `useForm` (e.g. a `UForm`'s state, copied in on submit), so
 * `form.isDirty` can't tell. `markClean()` starts comparing from the current value, e.g. after a
 * save that stays on the page.
 */
export function useIsDirty(source: () => unknown) {
    const saved = ref(JSON.stringify(source()));

    const isDirty = computed(() => JSON.stringify(source()) !== saved.value);

    function markClean() {
        saved.value = JSON.stringify(source());
    }

    return { isDirty, markClean };
}
