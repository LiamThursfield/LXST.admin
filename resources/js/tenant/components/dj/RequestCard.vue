<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { formatTime } from '@/lib/timezone';
import type {
    RequestQueueItemData,
    RequestStatus,
    SetlistSongType,
} from '@/types';

const props = defineProps<{
    item: RequestQueueItemData;
    timezone: string;
    busy: boolean;
    /** Big text and buttons, for booth mode */
    large?: boolean;
}>();

const emit = defineEmits<{ status: [status: RequestStatus] }>();

const { trans, transChoice } = useTrans();

const setlistColors: Record<SetlistSongType, 'success' | 'info' | 'error'> = {
    must_play: 'success',
    maybe: 'info',
    do_not_play: 'error',
};

/** The actions that make sense from each status; any other status can go back to pending */
const actions = computed(() => {
    const action = (
        status: RequestStatus,
        label: string,
        icon: string,
        color: 'primary' | 'neutral' | 'success' | 'error',
        variant: 'solid' | 'outline' | 'ghost' = 'outline',
    ) => ({
        status,
        label: trans(`dj.queue.actions.${label}`),
        icon,
        color,
        variant,
    });

    switch (props.item.status) {
        case 'pending':
            return [
                action(
                    'accepted',
                    'accept',
                    'i-lucide-check',
                    'primary',
                    'solid',
                ),
                action('played', 'played', 'i-lucide-disc-3', 'success'),
                action('declined', 'decline', 'i-lucide-x', 'error', 'ghost'),
            ];
        case 'accepted':
            return [
                action(
                    'played',
                    'played',
                    'i-lucide-disc-3',
                    'success',
                    'solid',
                ),
                action('declined', 'decline', 'i-lucide-x', 'error', 'ghost'),
                action(
                    'pending',
                    'undo',
                    'i-lucide-undo-2',
                    'neutral',
                    'ghost',
                ),
            ];
        default:
            return [
                action(
                    'pending',
                    'undo',
                    'i-lucide-undo-2',
                    'neutral',
                    'ghost',
                ),
            ];
    }
});

const details = computed(() =>
    [props.item.bpm, props.item.key?.camelot].filter(Boolean).join(' · '),
);
</script>

<template>
    <div
        class="border-default flex flex-col gap-3 rounded-lg border p-3"
        :class="[
            large ? 'p-5' : '',
            item.setlist_types.includes('do_not_play') ? 'border-error/60' : '',
        ]"
    >
        <div class="flex items-start justify-between gap-3">
            <div class="flex min-w-0 flex-col">
                <component
                    :is="item.song_url && !large ? Link : 'span'"
                    :href="item.song_url ?? undefined"
                    class="text-highlighted font-semibold"
                    :class="[
                        large ? 'text-2xl' : 'text-base',
                        item.song_url && !large ? 'hover:underline' : '',
                    ]"
                >
                    {{ item.title || item.artist }}
                </component>
                <span
                    v-if="item.title && item.artist"
                    class="text-muted"
                    :class="large ? 'text-xl' : 'text-sm'"
                    >{{ item.artist }}</span
                >
            </div>
            <UBadge
                color="primary"
                variant="subtle"
                :size="large ? 'xl' : 'md'"
                class="shrink-0"
            >
                {{ transChoice('dj.queue.votes', item.votes) }}
            </UBadge>
        </div>

        <div class="flex flex-wrap items-center gap-1.5">
            <UBadge
                v-for="type in item.setlist_types"
                :key="type"
                :color="setlistColors[type]"
                variant="subtle"
                :size="large ? 'lg' : 'sm'"
            >
                {{ trans(`enums.setlist_song_type.${type}`) }}
            </UBadge>
            <UBadge
                v-if="
                    item.was_do_not_play &&
                    !item.setlist_types.includes('do_not_play')
                "
                color="warning"
                variant="subtle"
                :size="large ? 'lg' : 'sm'"
            >
                {{ trans('dj.queue.was_do_not_play') }}
            </UBadge>
            <UBadge
                color="neutral"
                variant="outline"
                :size="large ? 'lg' : 'sm'"
            >
                {{
                    item.in_library
                        ? trans('dj.queue.in_library')
                        : trans('dj.queue.not_in_library')
                }}
            </UBadge>
            <span
                v-if="details"
                class="text-muted"
                :class="large ? 'text-lg' : 'text-xs'"
                >{{ details }}</span
            >
        </div>

        <div
            v-if="item.requester_name || item.pass || item.message"
            class="flex flex-col gap-1"
            :class="large ? 'text-lg' : 'text-sm'"
        >
            <span v-if="item.requester_name || item.pass" class="text-muted">
                <template v-if="item.requester_name">{{
                    trans('dj.queue.from', { name: item.requester_name })
                }}</template>
                <template v-if="item.requester_name && item.pass"> · </template>
                <template v-if="item.pass">{{
                    trans('dj.queue.via_pass', { pass: item.pass })
                }}</template>
            </span>
            <p v-if="item.message" class="whitespace-pre-line">
                “{{ item.message }}”
            </p>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-2">
            <span class="text-muted" :class="large ? 'text-base' : 'text-xs'">
                {{
                    item.played_at
                        ? trans('dj.queue.played_at', {
                              time: formatTime(item.played_at, timezone),
                          })
                        : trans('dj.queue.requested_at', {
                              time: formatTime(item.requested_at, timezone),
                          })
                }}
            </span>
            <div class="flex flex-wrap gap-2">
                <UButton
                    v-for="action in actions"
                    :key="action.status"
                    :size="large ? 'xl' : 'sm'"
                    :color="action.color"
                    :variant="action.variant"
                    :icon="action.icon"
                    :label="action.label"
                    :disabled="busy"
                    @click="emit('status', action.status)"
                />
            </div>
        </div>
    </div>
</template>
