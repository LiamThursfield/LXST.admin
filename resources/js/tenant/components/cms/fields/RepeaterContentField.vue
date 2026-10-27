<script setup lang="ts">
import { VueDraggable } from 'vue-draggable-plus';
import type { ContentValues, TemplateFieldData } from '@/types';
import { openCollapsible, setAllOpen, useContentState } from '../contentState';
import { useNestedTemplate } from '../nestedTemplates';
import RepeaterRow from './RepeaterRow.vue';

/** A row in the editor, with a client-side key so it keeps its identity while dragged. */
type Row = ContentValues & { _key?: number };

/**
 * A list of rows, each a set of the repeater template's fields, shown collapsed to a header
 * each. Existing rows start collapsed and new ones open. The server keeps only the template's
 * fields, so the `_key` used for reordering and remembering open rows isn't saved.
 */
const props = withDefaults(
    defineProps<{
        field: TemplateFieldData;
        /** Where this field's errors are, e.g. `content.slides`. */
        prefix: string;
        /** Where its rows' open state is remembered, e.g. `content.slides`. */
        stateKey?: string;
        errors?: Record<string, string | undefined>;
        disabled?: boolean;
    }>(),
    {
        stateKey: undefined,
        errors: () => ({}),
        disabled: false,
    },
);

const value = defineModel<Row[] | null>({ default: null });

const { trans, transChoice } = useTrans();

const state = useContentState();

const template = useNestedTemplate(() => props.field.settings.template_id);

const rowsStateKey = computed(() => props.stateKey ?? props.prefix);

if (!Array.isArray(value.value)) {
    value.value = [];
}

let nextKey =
    Math.max(-1, ...(value.value ?? []).map((row) => row._key ?? -1)) + 1;

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

const countLabel = computed(() =>
    maxItems.value === null
        ? transChoice('cms.content.summary.items', rows.value.length)
        : trans('cms.content.summary.items_of', {
              count: rows.value.length,
              max: maxItems.value,
          }),
);

const remainingLabel = computed(() =>
    maxItems.value === null
        ? null
        : transChoice(
              'cms.content.repeater.remaining',
              Math.max(0, maxItems.value - rows.value.length),
          ),
);

function rowStateKey(row: Row): string {
    return `${rowsStateKey.value}.${row._key}`;
}

/** Puts a new row at the index, open, so it's ready to fill in. */
function insertRow(index: number, row: Row) {
    const added = { ...row, _key: nextKey++ };

    openCollapsible(state, rowStateKey(added));
    rows.value = [
        ...rows.value.slice(0, index),
        added,
        ...rows.value.slice(index),
    ];
}

function addRow() {
    insertRow(rows.value.length, {});
}

function duplicateRow(index: number) {
    insertRow(index + 1, JSON.parse(JSON.stringify(rows.value[index])));
}

function moveRow(index: number, offset: number) {
    const target = index + offset;

    if (target < 0 || target >= rows.value.length) {
        return;
    }

    const moved = [...rows.value];
    [moved[index], moved[target]] = [moved[target], moved[index]];
    rows.value = moved;
}

function removeRow(index: number) {
    rows.value = rows.value.filter((_, rowIndex) => rowIndex !== index);
}
</script>

<template>
    <div class="flex flex-col gap-3">
        <div
            v-if="rows.length"
            class="flex flex-wrap items-center justify-between gap-2"
        >
            <p class="text-muted text-sm">{{ countLabel }}</p>
            <div v-if="rows.length > 1" class="flex gap-1">
                <UButton
                    color="neutral"
                    variant="ghost"
                    size="xs"
                    :label="trans('cms.content.expand_all')"
                    @click="setAllOpen(state, true, rowsStateKey)"
                />
                <UButton
                    color="neutral"
                    variant="ghost"
                    size="xs"
                    :label="trans('cms.content.collapse_all')"
                    @click="setAllOpen(state, false, rowsStateKey)"
                />
            </div>
        </div>
        <p v-else class="text-muted text-sm">
            {{ trans('cms.content.repeater.empty') }}
        </p>

        <VueDraggable
            v-model="rows"
            :disabled="disabled"
            handle=".drag-handle"
            :animation="150"
            class="flex flex-col gap-2"
        >
            <RepeaterRow
                v-for="(row, index) in rows"
                :key="row._key"
                v-model="rows[index]"
                :fields="template?.fields ?? []"
                :index="index"
                :count="rows.length"
                :prefix="`${prefix}.${index}`"
                :state-key="rowStateKey(row)"
                :errors="errors"
                :disabled="disabled"
                :can-add="canAdd"
                @duplicate="duplicateRow(index)"
                @move-up="moveRow(index, -1)"
                @move-down="moveRow(index, 1)"
                @add-below="insertRow(index + 1, {})"
                @remove="removeRow(index)"
            />
        </VueDraggable>

        <div v-if="!disabled" class="flex flex-wrap items-center gap-3">
            <UButton
                color="neutral"
                variant="subtle"
                icon="i-lucide-plus"
                :disabled="!canAdd"
                :label="trans('cms.content.repeater.add')"
                @click="addRow"
            />
            <p v-if="remainingLabel" class="text-muted text-sm">
                {{ remainingLabel }}
            </p>
        </div>
    </div>
</template>
