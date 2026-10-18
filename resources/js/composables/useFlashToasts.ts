/**
 * Shows the messages controllers flash (e.g. `->with('success', ...)`) as toasts, for as long as
 * the calling component (a layout) is mounted. Flash data isn't kept in the browser history, so
 * each message is shown once.
 */
export function useFlashToasts(): void {
    const toast = useToast();

    const removeFlashListener = router.on('flash', (event) => {
        const { success, error } = event.detail.flash;

        if (success) {
            toast.add({
                title: success,
                icon: 'i-lucide-check',
                color: 'success',
            });
        }

        if (error) {
            toast.add({
                title: error,
                icon: 'i-lucide-circle-alert',
                color: 'error',
            });
        }
    });

    onUnmounted(removeFlashListener);
}
