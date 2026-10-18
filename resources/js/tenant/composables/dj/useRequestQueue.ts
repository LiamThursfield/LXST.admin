import { usePoll } from '@inertiajs/vue3';
import { updateMode } from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/RequestQueueController';
import { updateStatus } from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/RequestQueueController';
import type {
    RequestQueueGig,
    RequestQueueItemData,
    RequestStatus,
} from '@/types';

/**
 * The request queue's live updates and actions, shared by the admin page and booth mode
 */
export function useRequestQueue(gig: () => RequestQueueGig | null) {
    // New requests show within a few seconds; polling pauses while the tab is in the background
    usePoll(5000, { only: ['gig', 'requests'] });

    // Requests being changed, so their buttons show as busy
    const busy = ref<number[]>([]);

    function setStatus(item: RequestQueueItemData, status: RequestStatus) {
        router.patch(
            updateStatus.url(item.id),
            { status },
            {
                preserveScroll: true,
                preserveState: true,
                only: ['requests'],
                onStart: () => busy.value.push(item.id),
                onFinish: () =>
                    (busy.value = busy.value.filter((id) => id !== item.id)),
            },
        );
    }

    function setMode(mode: 'open' | 'closed' | null) {
        const current = gig();

        if (current) {
            router.patch(
                updateMode.url(current.id),
                { requests_mode: mode },
                { preserveScroll: true, preserveState: true },
            );
        }
    }

    return { busy, setStatus, setMode };
}
