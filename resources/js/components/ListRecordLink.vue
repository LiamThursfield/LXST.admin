<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { recordUrl } from '@/lib/list';

/**
 * A list row's name, linked to the record from the row's actions (its edit page when the user can
 * edit it, or else its page), or plain text when the user can do neither.
 */
const props = defineProps<{
    actions?: Array<{ url: string; method: string }>;
}>();

const url = computed(() => recordUrl(props.actions));
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
