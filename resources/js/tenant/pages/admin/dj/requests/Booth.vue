<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { index } from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/RequestQueueController';
import BoothLayout from '@/layouts/BoothLayout.vue';
import RequestCard from '@/tenant/components/dj/RequestCard.vue';
import RequestModeControl from '@/tenant/components/dj/RequestModeControl.vue';
import { useRequestQueue } from '@/tenant/composables/dj/useRequestQueue';
import type { RequestQueueData, RequestQueueGig } from '@/types';

defineOptions({ layout: BoothLayout });

const props = defineProps<{
    gig: RequestQueueGig | null;
    requests: RequestQueueData | null;
}>();

const { trans } = useTrans();
const { busy, setStatus, setMode } = useRequestQueue(() => props.gig);

const exitUrl = computed(() =>
    index.url(props.gig ? { query: { gig: props.gig.id } } : {}),
);
</script>

<template>
    <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 sm:p-6">
        <Head :title="trans('dj.queue.booth')" />

        <header class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-highlighted text-2xl font-bold sm:text-3xl">
                {{ gig?.name ?? trans('dj.queue.title') }}
            </h1>
            <UButton
                as="Link"
                :href="exitUrl"
                size="lg"
                color="neutral"
                variant="ghost"
                icon="i-lucide-minimize"
                :label="trans('dj.queue.exit_booth')"
            />
        </header>

        <p v-if="!gig || !requests" class="text-muted text-lg">
            {{ trans('dj.queue.no_gig') }}
        </p>

        <template v-else>
            <RequestModeControl :gig="gig" large @mode="setMode" />

            <section
                v-for="status in ['pending', 'accepted'] as const"
                :key="status"
                class="flex flex-col gap-4"
            >
                <h2 class="text-highlighted text-xl font-semibold">
                    {{ trans(`dj.queue.sections.${status}`) }}
                    <span class="text-muted font-normal">{{
                        requests[status].length
                    }}</span>
                </h2>
                <RequestCard
                    v-for="item in requests[status]"
                    :key="item.id"
                    :item="item"
                    :timezone="gig.timezone"
                    :busy="busy.includes(item.id)"
                    large
                    @status="(next) => setStatus(item, next)"
                />
                <p v-if="!requests[status].length" class="text-muted text-lg">
                    {{ trans('dj.queue.empty') }}
                </p>
            </section>

            <section v-if="requests.played.length" class="flex flex-col gap-4">
                <h2 class="text-highlighted text-xl font-semibold">
                    {{ trans('dj.queue.sections.played') }}
                </h2>
                <RequestCard
                    v-for="item in requests.played.slice(0, 5)"
                    :key="item.id"
                    :item="item"
                    :timezone="gig.timezone"
                    :busy="busy.includes(item.id)"
                    large
                    @status="(next) => setStatus(item, next)"
                />
            </section>
        </template>
    </div>
</template>
