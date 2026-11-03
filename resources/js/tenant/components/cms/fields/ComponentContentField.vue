<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import {
    edit as editSavedComponent,
    storeReusable,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Cms/SavedComponentController';
import type {
    ContentValues,
    SavedComponentOption,
    TemplateFieldData,
} from '@/types';
import ContentEditor from '../ContentEditor.vue';
import {
    componentLevel,
    fillNestedValues,
    opensInPanel,
    useContentPanel,
    useInsideContentPanel,
} from '../contentPanel';
import { textValues } from '../contentSummary';
import {
    isSavedReference,
    nestedTemplatesKey,
    savedComponentKey,
    useNestedTemplate,
    useSavedComponents,
} from '../nestedTemplates';

/**
 * One set of a component template's fields, stored as an object keyed by their slugs, or a reference
 * to a saved component, whose values are shown read-only. If it's nested too deeply to draw inline,
 * it's a summary that opens the fields in the side panel.
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
const savedComponents = useSavedComponents();

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
        savedComponents?.items.value,
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
    panel?.push({
        ...componentLevel(
            props.field,
            props.parentValues!,
            props.prefix,
            props.stateKey ?? props.prefix,
            nestedTemplates.value,
        ),
        readonly: props.disabled,
    });
}

/** The saved components this field can use: those of its template. */
const options = computed(() =>
    (savedComponents?.items.value ?? []).filter(
        (item) => item.template_id === props.field.settings.template_id,
    ),
);

const isSaved = computed(() => isSavedReference(value.value));

const selectedId = computed<number | null>({
    get: () => value.value?.[savedComponentKey] ?? null,
    set: (id) => {
        value.value = { [savedComponentKey]: id };
    },
});

const selected = computed(() =>
    options.value.find((item) => item.id === selectedId.value),
);

/** It refers to one that's gone, or that has a different template now the field's was changed. */
const isGone = computed(
    () => isSaved.value && selectedId.value !== null && !selected.value,
);

const canChoose = computed(
    () =>
        !!savedComponents &&
        !props.disabled &&
        (options.value.length > 0 ||
            savedComponents.canManage ||
            isSaved.value),
);

const referenceError = computed(
    () => props.errors[`${props.prefix}.${savedComponentKey}`],
);

/** A copy, as editors fill in empty values and row keys on what they're given. */
const preview = computed<ContentValues | null>(() =>
    selected.value ? copyOf(selected.value.content) : null,
);

function copyOf(values: ContentValues): ContentValues {
    return JSON.parse(JSON.stringify(values));
}

/** Values ready to edit, with empty values and row keys filled in as the editors would. */
function customValues(values: ContentValues): ContentValues {
    fillNestedValues(
        template.value?.fields ?? [],
        values,
        nestedTemplates.value,
    );

    return values;
}

/** What was typed before switching to a saved component, in case they switch back. */
let rememberedCustom: ContentValues | null = null;

const source = computed({
    get: () => (isSaved.value ? 'saved' : 'custom'),
    set: (chosen: string) => {
        if (chosen === 'saved') {
            rememberedCustom = value.value;
            value.value = { [savedComponentKey]: null };
        } else {
            value.value = rememberedCustom ?? customValues({});
        }
    },
});

const sourceItems = computed(() => [
    { label: savedComponents?.customLabel ?? '', value: 'custom' },
    { label: trans('cms.content.saved.use_saved'), value: 'saved' },
]);

const optionItems = computed(() =>
    options.value.map((item) => ({ label: item.name, value: item.id })),
);

/** Gives the field its own copy of the saved values, so it no longer changes with them. */
function detach() {
    if (selected.value) {
        value.value = customValues(copyOf(selected.value.content));
    }
}

const saveOpen = ref(false);
const saveFailed = ref(false);

const saveRequest = useHttp({
    name: '',
    slug: '',
    template_id: null as number | null,
    content: {} as ContentValues,
});

const saveNameError = computed(
    () => saveRequest.errors.name ?? saveRequest.errors.slug,
);

/** Problems with the values themselves, which are fixed on the field rather than in the dialog. */
const saveValueErrors = computed(() =>
    Object.entries(saveRequest.errors as Record<string, string | undefined>)
        .filter(([key]) => key.startsWith('content'))
        .map(([, message]) => message),
);

function openSave() {
    saveRequest.reset();
    saveRequest.clearErrors();
    saveFailed.value = false;
    saveOpen.value = true;
}

async function submitSave() {
    saveFailed.value = false;
    saveRequest.template_id = props.field.settings.template_id ?? null;
    saveRequest.content = value.value ?? {};

    try {
        const created = (await saveRequest.post(storeReusable.url())) as
            SavedComponentOption | undefined;

        // Nothing comes back from a failed validation; saveRequest.errors has what went wrong
        if (!created) {
            return;
        }

        savedComponents?.items.value.push(created);
        savedComponents?.items.value.sort((first, second) =>
            first.name.localeCompare(second.name),
        );
        rememberedCustom = value.value;
        selectedId.value = created.id;
        saveOpen.value = false;
    } catch {
        saveFailed.value = true;
    }
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
    <div
        v-else
        class="flex flex-col gap-4"
        :class="{ 'border-default rounded-md border p-4': framed }"
    >
        <div
            v-if="canChoose"
            class="flex flex-wrap items-center justify-between gap-2"
        >
            <URadioGroup
                v-if="options.length > 0 || isSaved"
                v-model="source"
                :items="sourceItems"
                :aria-label="trans('cms.content.saved.source')"
                orientation="horizontal"
            />
            <UTooltip
                v-if="selected"
                :text="trans('cms.content.saved.detach_help')"
            >
                <UButton
                    color="neutral"
                    variant="ghost"
                    size="sm"
                    icon="i-lucide-unlink"
                    :label="trans('cms.content.saved.detach')"
                    @click="detach"
                />
            </UTooltip>
            <UButton
                v-else-if="!isSaved && savedComponents?.canManage"
                class="ms-auto"
                color="neutral"
                variant="ghost"
                size="sm"
                icon="i-lucide-bookmark-plus"
                :label="trans('cms.content.saved.save_as_reusable')"
                @click="openSave"
            />
        </div>

        <template v-if="isSaved">
            <UFormField
                v-if="!disabled"
                :label="trans('cms.content.saved.choose')"
                :error="referenceError"
                required
            >
                <USelect
                    v-model.nullable="selectedId"
                    :items="optionItems"
                    :placeholder="trans('cms.content.saved.choose_placeholder')"
                    class="w-full"
                />
            </UFormField>

            <UAlert
                v-if="isGone"
                color="warning"
                variant="subtle"
                icon="i-lucide-triangle-alert"
                :description="trans('cms.content.saved.gone')"
            />

            <template v-if="selected">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="min-w-0 text-sm">
                        <p v-if="disabled" class="text-highlighted font-medium">
                            {{
                                trans('cms.content.saved.using', {
                                    name: selected.name,
                                })
                            }}
                        </p>
                        <p class="text-muted">
                            {{ trans('cms.content.saved.read_only_help') }}
                        </p>
                    </div>
                    <UButton
                        v-if="savedComponents?.canManage"
                        color="neutral"
                        variant="ghost"
                        size="sm"
                        icon="i-lucide-external-link"
                        :label="trans('cms.content.saved.edit')"
                        :to="
                            editSavedComponent.url({
                                saved_component: selected.id,
                            })
                        "
                        target="_blank"
                    />
                </div>

                <ContentEditor
                    v-if="template && preview"
                    :model-value="preview"
                    :fields="template.fields ?? []"
                    :sections="template.sections ?? []"
                    :prefix="prefix"
                    :state-key="`${stateKey ?? prefix}:saved`"
                    readonly
                />
            </template>
        </template>

        <ContentEditor
            v-else-if="template && value"
            v-model="value"
            :fields="template.fields ?? []"
            :sections="template.sections ?? []"
            :prefix="prefix"
            :state-key="stateKey"
            :errors="errors"
            :readonly="disabled"
        />

        <UModal
            v-model:open="saveOpen"
            :title="trans('cms.content.saved.save_title')"
            :description="trans('cms.content.saved.save_description')"
        >
            <template #body>
                <form class="flex flex-col gap-4" @submit.prevent="submitSave">
                    <UFormField
                        :label="trans('cms.content.saved.save_name')"
                        :error="saveNameError"
                        required
                    >
                        <UInput
                            v-model="saveRequest.name"
                            class="w-full"
                            autofocus
                        />
                    </UFormField>

                    <p
                        v-for="message in saveValueErrors"
                        :key="message"
                        class="text-error text-sm"
                    >
                        {{ message }}
                    </p>
                    <p v-if="saveFailed" class="text-error text-sm">
                        {{ trans('cms.content.saved.save_failed') }}
                    </p>

                    <div class="flex justify-end gap-2">
                        <UButton
                            color="neutral"
                            variant="subtle"
                            :label="trans('cms.content.saved.save_cancel')"
                            @click="saveOpen = false"
                        />
                        <UButton
                            type="submit"
                            :loading="saveRequest.processing"
                            :label="trans('cms.content.saved.save_submit')"
                        />
                    </div>
                </form>
            </template>
        </UModal>
    </div>
</template>
