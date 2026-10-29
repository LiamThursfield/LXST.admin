<script setup lang="ts" generic="T extends { uid: number }">
import { VueDraggable } from 'vue-draggable-plus';

/**
 * The reorderable list of fields in a field builder (CRM forms, CMS templates). Each item needs a
 * client-side `uid` so it keeps its identity while being dragged. Render each item through the
 * default slot, with a `.drag-handle` element to drag it by. Lists sharing a `group` can drag
 * fields between them, e.g. between a template's sections; an empty one stays a place to drop.
 */
withDefaults(
    defineProps<{
        emptyText: string;
        addLabel: string;
        /** An error for the list as a whole, e.g. too many fields. */
        error?: string;
        readonly?: boolean;
        /** Lists with the same group can drag fields between them. */
        group?: string;
    }>(),
    {
        error: undefined,
        readonly: false,
        group: undefined,
    },
);

defineEmits<{
    add: [];
}>();

const items = defineModel<T[]>({ required: true });

defineSlots<{
    default(props: { item: T; index: number }): unknown;
}>();
</script>

<template>
    <div class="flex flex-col gap-3">
        <p v-if="error" class="text-error text-sm">
            {{ error }}
        </p>

        <p v-if="!items.length && !group" class="text-muted text-sm">
            {{ emptyText }}
        </p>

        <div class="relative">
            <VueDraggable
                v-model="items"
                :disabled="readonly"
                :group="group"
                handle=".drag-handle"
                :animation="150"
                class="flex flex-col gap-3"
                :class="{
                    'border-default min-h-12 rounded-md border border-dashed':
                        group && !items.length,
                }"
            >
                <div v-for="(item, index) in items" :key="item.uid">
                    <slot :item="item" :index="index" />
                </div>
            </VueDraggable>

            <p
                v-if="group && !items.length"
                class="text-muted pointer-events-none absolute inset-0 flex items-center justify-center px-3 text-center text-sm"
            >
                {{ emptyText }}
            </p>
        </div>

        <UButton
            v-if="!readonly"
            class="w-fit"
            color="neutral"
            variant="subtle"
            icon="i-lucide-plus"
            :label="addLabel"
            @click="$emit('add')"
        />
    </div>
</template>
