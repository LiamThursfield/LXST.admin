<script setup lang="ts">
import type { RequestQueueGig } from '@/types';

defineProps<{
    gig: RequestQueueGig;
    large?: boolean;
}>();

const emit = defineEmits<{ mode: [mode: 'open' | 'closed' | null] }>();

const { trans } = useTrans();
</script>

<template>
    <div class="flex flex-wrap items-center gap-2">
        <UBadge
            :color="gig.requests_open ? 'success' : 'neutral'"
            variant="subtle"
            :size="large ? 'xl' : 'lg'"
        >
            {{
                gig.requests_open
                    ? trans('dj.queue.requests_open')
                    : trans('dj.queue.requests_closed')
            }}
        </UBadge>
        <UButton
            v-if="gig.requests_open"
            :size="large ? 'lg' : 'sm'"
            color="neutral"
            variant="outline"
            icon="i-lucide-lock"
            :label="trans('dj.queue.close')"
            @click="emit('mode', 'closed')"
        />
        <UButton
            v-else
            :size="large ? 'lg' : 'sm'"
            icon="i-lucide-lock-open"
            :label="trans('dj.queue.open')"
            @click="emit('mode', 'open')"
        />
        <UButton
            v-if="gig.requests_mode !== null"
            :size="large ? 'lg' : 'sm'"
            color="neutral"
            variant="ghost"
            :label="
                trans('dj.queue.follow_default', {
                    mode: gig.default_requests_mode,
                })
            "
            @click="emit('mode', null)"
        />
    </div>
</template>
