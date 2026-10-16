<script setup lang="ts">
import { VueDraggable } from 'vue-draggable-plus';
import type { ContentValues, TemplateFieldData } from '@/types';
import ContentEditor from '../ContentEditor.vue';
import { useNestedTemplate } from '../nestedTemplates';

/** A row in the editor, with a client-side key so it keeps its identity while dragged. */
type Row = ContentValues & { _key?: number };

/**
 * A list of rows, each a set of the repeater template's fields. The server keeps only the
 * template's fields, so the `_key` used for reordering isn't saved.
 */
const props = defineProps<{
    field: TemplateFieldData;
    /** Where this field's errors are, e.g. `content.slides`. */
    prefix: string;
    errors?: Record<string, string | undefined>;
    disabled?: boolean;
}>();

const value = defineModel<Row[] | null>({ default: null });

const { trans } = useTrans();

const template = useNestedTemplate(() => props.field.settings.template_id);

let nextKey = 0;

if (!Array.isArray(value.value)) {
    value.value = [];
}

for (const row of value.value ?? []) {
    row._key ??= nextKey++;
}

const rows = computed({
    get: () => value.value ?? [],
    set: (newRows: Row[]) => {
        value.value = newRows;
    },
});

const maxItems = computed(() => props.field.settings.max_items ?? null);

const canAdd = computed(
    () => maxItems.value === null || rows.value.length < maxItems.value,
);

function addRow() {
    rows.value = [...rows.value, { _key: nextKey++ }];
}

function removeRow(index: number) {
    rows.value = rows.value.filter((_, rowIndex) => rowIndex !== index);
}
</script>

<template>
    <div class="flex flex-col gap-3">
        <p v-if="!rows.length" class="text-muted text-sm">
            {{ trans('cms.content.repeater.empty') }}
        </p>

        <VueDraggable
            v-model="rows"
            :disabled="disabled"
            handle=".drag-handle"
            :animation="150"
            class="flex flex-col gap-3"
        >
            <UCard
                v-for="(row, index) in rows"
                :key="row._key"
                :ui="{ body: 'p-4 sm:p-4' }"
            >
                <div class="flex gap-3">
                    <UButton
                        v-if="!disabled"
                        class="drag-handle cursor-grab self-start"
                        color="neutral"
                        variant="ghost"
                        icon="i-lucide-grip-vertical"
                        :aria-label="trans('cms.content.repeater.drag')"
                    />

                    <div class="flex-1">
                        <p class="text-muted mb-3 text-xs font-medium">
                            {{
                                trans('cms.content.repeater.row', {
                                    number: index + 1,
                                })
                            }}
                        </p>
                        <ContentEditor
                            v-if="template"
                            v-model="rows[index]"
                            :fields="template.fields ?? []"
                            :prefix="`${prefix}.${index}`"
                            :errors="errors"
                            :readonly="disabled"
                        />
                    </div>

                    <UButton
                        v-if="!disabled"
                        class="self-start"
                        color="neutral"
                        variant="ghost"
                        icon="i-lucide-trash"
                        :aria-label="trans('cms.content.repeater.remove')"
                        @click="removeRow(index)"
                    />
                </div>
            </UCard>
        </VueDraggable>

        <UButton
            v-if="!disabled"
            class="w-fit"
            color="neutral"
            variant="subtle"
            icon="i-lucide-plus"
            :disabled="!canAdd"
            :label="trans('cms.content.repeater.add')"
            @click="addRow"
        />
    </div>
</template>
