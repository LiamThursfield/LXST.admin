<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { recordUrl } from '@/lib/list';

/**
 * A list row's name, linked to the record from the row's actions (its edit page when the user can
 * edit it, or else its page; its page first with `toRecordPage`), or plain text when the user can
 * do neither.
 */
const props = defineProps<{
    actions?: Array<{ url: string; method: string }>;
    toRecordPage?: boolean;
}>();

const url = computed(() => recordUrl(props.actions, props.toRecordPage));
</script>

<template>
    <Link
        v-if="url"
        :href="url"
        class="text-highlighted font-medium hover:underline"
    >
        <slot />
    </Link>
    <span v-else><slot /></span>
</template>
