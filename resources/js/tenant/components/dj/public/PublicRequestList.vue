<script setup lang="ts">
import {
    index as listRequests,
    upvote,
} from '@/actions/App/Http/Controllers/Tenant/Api/V1/Dj/GigRequestController';
import { PublicApiError, publicApi } from '@/lib/publicApi';
import type { PublicRequestData } from '@/types';

const props = defineProps<{
    gigId: string;
    guestToken: string | null;
}>();

const { trans, transChoice } = useTrans();
const toast = useToast();

const requests = ref<PublicRequestData[]>([]);
const played = ref<PublicRequestData[]>([]);
const allowUpvotes = ref(false);
const loaded = ref(false);
const voting = ref<number | null>(null);

async function load() {
    try {
        const response = await publicApi<{
            data: {
                allow_upvotes: boolean;
                requests: PublicRequestData[];
                played: PublicRequestData[];
            };
        }>(listRequests(props.gigId), { guestToken: props.guestToken });

        requests.value = response.data.requests;
        played.value = response.data.played;
        allowUpvotes.value = response.data.allow_upvotes;
    } catch {
        // Kept as it was; the next refresh tries again
    } finally {
        loaded.value = true;
    }
}

async function vote(item: PublicRequestData) {
    voting.value = item.id;

    try {
        const response = await publicApi<{ data: { message: string } }>(
            upvote({ gig: props.gigId, item: item.id }),
            { body: {}, guestToken: props.guestToken },
        );
        toast.add({ title: response.data.message, icon: 'i-lucide-thumbs-up' });
        await load();
    } catch (exception) {
        toast.add({
            title:
                exception instanceof PublicApiError
                    ? exception.message
                    : trans('dj.hosted.error'),
            color: 'error',
        });
    } finally {
        voting.value = null;
    }
}

// Keeps up with other visitors' requests and votes
let timer: ReturnType<typeof setInterval> | undefined;

onMounted(() => {
    load();
    timer = setInterval(load, 15000);
});
onUnmounted(() => clearInterval(timer));

defineExpose({ load });
</script>

<template>
    <div class="flex flex-col gap-6">
        <UPageCard
            :title="trans('dj.hosted.list.title')"
            :description="trans('dj.hosted.list.help')"
            variant="subtle"
        >
            <ul
                v-if="requests.length"
                class="divide-default flex flex-col divide-y"
            >
                <li
                    v-for="item in requests"
                    :key="item.id"
                    class="flex items-center justify-between gap-3 py-3"
                >
                    <div class="flex min-w-0 flex-col">
                        <span class="text-highlighted truncate font-medium">{{
                            item.title || item.artist
                        }}</span>
                        <span class="text-muted truncate text-sm">
                            <template v-if="item.title">{{
                                item.artist
                            }}</template>
                            <template v-if="item.requester_name">
                                ·
                                {{
                                    trans('dj.hosted.list.by', {
                                        name: item.requester_name,
                                    })
                                }}
                            </template>
                            <template v-if="item.status === 'accepted'">
                                · {{ trans('dj.hosted.list.accepted') }}
                            </template>
                        </span>
                    </div>
                    <UButton
                        v-if="allowUpvotes"
                        :color="item.voted ? 'primary' : 'neutral'"
                        :variant="item.voted ? 'soft' : 'outline'"
                        icon="i-lucide-thumbs-up"
                        :label="String(item.votes)"
                        :aria-label="
                            item.voted
                                ? trans('dj.hosted.list.voted')
                                : trans('dj.hosted.list.upvote')
                        "
                        :disabled="item.voted"
                        :loading="voting === item.id"
                        @click="vote(item)"
                    />
                    <UBadge v-else color="neutral" variant="subtle">
                        {{ transChoice('dj.queue.votes', item.votes) }}
                    </UBadge>
                </li>
            </ul>
            <p v-else-if="loaded" class="text-muted text-sm">
                {{ trans('dj.hosted.list.empty') }}
            </p>
            <USkeleton v-else class="h-24 w-full" />
        </UPageCard>

        <UPageCard
            v-if="played.length"
            :title="trans('dj.hosted.list.played')"
            variant="subtle"
        >
            <ul class="flex flex-col gap-1 text-sm">
                <li v-for="item in played" :key="item.id">
                    <span class="text-highlighted">{{
                        item.title || item.artist
                    }}</span>
                    <span v-if="item.title" class="text-muted">
                        · {{ item.artist }}</span
                    >
                </li>
            </ul>
        </UPageCard>
    </div>
</template>
