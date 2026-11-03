<script setup lang="ts">
import type { ContentValues, TemplateFieldData } from '@/types';
import ContentEditor from '../ContentEditor.vue';
import {
    componentLevel,
    opensInPanel,
    useContentPanel,
    useInsideContentPanel,
} from '../contentPanel';
import { textValues } from '../contentSummary';
import { nestedTemplatesKey, useNestedTemplate } from '../nestedTemplates';

/**
 * One set of a component template's fields, stored as an object keyed by their slugs. If it's
 * nested too deeply to draw inline, it's a summary that opens the fields in the side panel.
 */
const props = withDefaults(
    defineProps<{
        field: TemplateFieldData;
        /** Where this field's errors are, e.g. `content.call_to_action`. */
        prefix: string;
        /** Where open repeater rows inside it are remembered, e.g. `content.call_to_action`. */
        stateKey?: string;
        errors?: Record<string, string | undefined>;
        disabled?: boolean;
        /** Bordered, to set it apart from the fields around it; not when it has a card of its own. */
        framed?: boolean;
        /** Whether it's at the top of the editor, where it's always drawn inline. */
        isTopLevel?: boolean;
        /** The values holding this field, so it can be opened in the side panel. */
        parentValues?: ContentValues;
    }>(),
    {
        isTopLevel: false,
        parentValues: undefined,
        stateKey: undefined,
        errors: () => ({}),
        disabled: false,
        framed: true,
    },
);

const value = defineModel<ContentValues | null>({ default: null });

// The nested editor fills in the object's keys, so it needs one to start from
if (value.value === null || typeof value.value !== 'object') {
    value.value = {};
}

const { trans } = useTrans();

const template = useNestedTemplate(() => props.field.settings.template_id);

const panel = useContentPanel();
const insidePanel = useInsideContentPanel();
const nestedTemplates = inject(nestedTemplatesKey, ref([]));

const inPanel = computed(
    () =>
        !!panel &&
        !!props.parentValues &&
        !props.isTopLevel &&
        !!template.value &&
        opensInPanel(template.value.fields ?? [], insidePanel),
);

const texts = computed(() =>
    textValues(
        template.value?.fields ?? [],
        value.value,
        nestedTemplates.value,
    ),
);

/** The first error inside it, shown on the summary. */
const error = computed(
    () =>
        Object.entries(props.errors).find(
            ([key, message]) => message && key.startsWith(`${props.prefix}.`),
        )?.[1],
);

function openInPanel() {
    panel?.push(
        componentLevel(
            props.field,
            props.parentValues!,
            props.prefix,
            props.stateKey ?? props.prefix,
            nestedTemplates.value,
        ),
    );
}
</script>

<template>
    <button
        v-if="inPanel"
        type="button"
        class="focus-visible:outline-primary flex w-full items-center gap-2.5 rounded-md border px-3 py-2.5 text-left focus-visible:outline-2"
        :class="error ? 'border-error bg-error/5' : 'border-default bg-default'"
        aria-haspopup="dialog"
        v-bind="error ? { 'data-content-error': '' } : {}"
        @click="openInPanel"
    >
        <span
            class="min-w-0 shrink truncate text-sm font-medium"
            :class="texts[0] ? 'text-highlighted' : 'text-muted italic'"
        >
            {{ texts[0] ?? trans('cms.content.summary.empty') }}
        </span>
        <span
            class="min-w-0 flex-1 truncate text-sm"
            :class="error ? 'text-error' : 'text-muted'"
        >
            {{ error ?? texts[1] }}
        </span>
        <UIcon
            name="i-lucide-chevron-right"
            class="text-muted size-4 shrink-0"
        />
    </button>
    <div v-else :class="{ 'border-default rounded-md border p-4': framed }">
        <ContentEditor
            v-if="template && value"
            v-model="value"
            :fields="template.fields ?? []"
            :sections="template.sections ?? []"
            :prefix="prefix"
            :state-key="stateKey"
            :errors="errors"
            :readonly="disabled"
        />
    </div>
</template>
