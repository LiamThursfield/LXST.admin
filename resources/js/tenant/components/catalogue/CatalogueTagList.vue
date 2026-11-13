<script setup lang="ts">
import type { CatalogueTagOption } from '@/types';

/**
 * An item's tags as small badges, with their icons. Allergens are picked out in another colour.
 */
const props = defineProps<{
    tagIds: number[];
    tags: CatalogueTagOption[];
}>();

const chosen = computed(() =>
    props.tags.filter((tag) => props.tagIds.includes(tag.value)),
);
</script>

<template>
    <div v-if="chosen.length" class="flex flex-wrap gap-1">
        <UBadge
            v-for="tag in chosen"
            :key="tag.value"
            :icon="tag.icon ?? undefined"
            :label="tag.label"
            :color="tag.kind === 'allergen' ? 'warning' : 'neutral'"
            variant="subtle"
            size="sm"
        />
    </div>
</template>
