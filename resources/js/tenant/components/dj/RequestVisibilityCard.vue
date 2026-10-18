<script setup lang="ts">
const props = withDefaults(
    defineProps<{
        /** Hidden itself */
        hidden: boolean;
        /** Hidden with its artist, so showing it needs the artist shown */
        hiddenWithArtist?: boolean;
        /** Whether visitors can find it after every rule; left out where it doesn't apply */
        requestable?: boolean | null;
        help: string;
        /** Where the hidden flag is changed */
        url: string;
        canManage: boolean;
    }>(),
    // Null, not false, when left out: a missing boolean prop would otherwise read as false
    { hiddenWithArtist: false, requestable: null },
);

const { trans } = useTrans();
const busy = ref(false);

const status = computed(() => {
    if (props.hidden) {
        return {
            label: trans('dj.requests_visibility.hidden'),
            color: 'neutral' as const,
            help: null,
        };
    }

    if (props.hiddenWithArtist) {
        return {
            label: trans('dj.requests_visibility.hidden_with_artist'),
            color: 'neutral' as const,
            help: trans('dj.requests_visibility.artist_hidden_help'),
        };
    }

    if (props.requestable === false) {
        return {
            label: trans('dj.requests_visibility.not_requestable'),
            color: 'warning' as const,
            help: trans('dj.requests_visibility.not_requestable_help'),
        };
    }

    return {
        label: trans('dj.requests_visibility.requestable'),
        color: 'success' as const,
        help: null,
    };
});

function setHidden(hidden: boolean) {
    router.patch(
        props.url,
        { hidden },
        {
            preserveScroll: true,
            onStart: () => (busy.value = true),
            onFinish: () => (busy.value = false),
        },
    );
}
</script>

<template>
    <UPageCard
        :title="trans('dj.requests_visibility.title')"
        :description="help"
        variant="subtle"
    >
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-col gap-1">
                <UBadge :color="status.color" variant="subtle" class="w-fit">
                    {{ status.label }}
                </UBadge>
                <span v-if="status.help" class="text-muted text-sm">{{
                    status.help
                }}</span>
            </div>
            <UButton
                v-if="canManage"
                size="sm"
                color="neutral"
                variant="outline"
                :icon="hidden ? 'i-lucide-eye' : 'i-lucide-eye-off'"
                :label="
                    hidden
                        ? trans('dj.requests_visibility.show')
                        : trans('dj.requests_visibility.hide')
                "
                :loading="busy"
                @click="setHidden(!hidden)"
            />
        </div>
    </UPageCard>
</template>
