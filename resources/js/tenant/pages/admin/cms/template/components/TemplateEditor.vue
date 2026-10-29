<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { VueDraggable } from 'vue-draggable-plus';
import {
    index,
    store,
    update,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Cms/TemplateController';
import type { EditorTab } from '@/components/EditorLayout.vue';
import BuilderFieldList from '@/tenant/components/builder/BuilderFieldList.vue';
import type { BuilderFieldRow } from '@/tenant/components/builder/BuilderFieldList.vue';
import BuilderFieldPanel from '@/tenant/components/builder/BuilderFieldPanel.vue';
import FieldTypePicker from '@/tenant/components/builder/FieldTypePicker.vue';
import type { PickerFieldTypeGroup } from '@/tenant/components/builder/FieldTypePicker.vue';
import type {
    NestedTemplateOption,
    SelectItem,
    TemplateData,
    TemplateFieldData,
    TemplateFieldTypeOption,
    TemplateSectionData,
    TemplateType,
} from '@/types';
import {
    templateFieldTypeGroups,
    templateFieldTypeIcon,
    templateFieldTypeMeta,
} from './fieldTypes';
import TemplateFieldEditor from './TemplateFieldEditor.vue';

/**
 * Creates, edits or shows a template. The Fields tab lists the fields compactly, grouped by
 * section, beside the settings of the one chosen; the Details tab has the template's own details.
 */
const props = withDefaults(
    defineProps<{
        template?: TemplateData;
        types: SelectItem[];
        fieldTypes: TemplateFieldTypeOption[];
        nestedTemplates: NestedTemplateOption[];
        organisationUnitTypes?: SelectItem[];
        /** Other templates use this one, so its type can't change. */
        isInUse?: boolean;
        readonly?: boolean;
    }>(),
    {
        template: undefined,
        organisationUnitTypes: () => [],
        isInUse: false,
        readonly: false,
    },
);

const { trans } = useTrans();

/** A field in the builder, with a client-side key so the list can be reordered. */
type BuilderField = TemplateFieldData & { uid: number };

/** A section in the builder, holding its fields, with a client-side key so it can be reordered. */
type BuilderSection = TemplateSectionData & {
    uid: number;
    fields: BuilderField[];
};

let nextUid = 0;

function toBuilderField(field: TemplateFieldData): BuilderField {
    return { ...field, settings: { ...field.settings }, uid: nextUid++ };
}

const template = props.template;

/** The saved fields in a section, or with no section (null). */
function fieldsIn(sectionId: number | null): BuilderField[] {
    return (template?.fields ?? [])
        .filter((field) => (field.section_id ?? null) === sectionId)
        .map(toBuilderField);
}

const form = useForm({
    type: (template?.type ?? 'page') as TemplateType,
    name: template?.name ?? '',
    slug: template?.slug ?? '',
    description: template?.description ?? '',
    /** The fields with no section, which sit above the first section. */
    fields: fieldsIn(null) as BuilderField[],
    sections: (template?.sections ?? []).map((section) => ({
        ...section,
        uid: nextUid++,
        fields: fieldsIn(section.id),
    })) as BuilderSection[],
});

/** Server validation errors, including nested ones such as `sections.0.fields.1.name`. */
const errors = computed(
    () => form.errors as Record<string, string | undefined>,
);

/** The field types this type of template allows. */
const allowedFieldTypes = computed(() =>
    props.fieldTypes.filter((type) => type.templateTypes.includes(form.type)),
);

/** A template can't nest itself. */
const nestedTemplateOptions = computed(() =>
    props.nestedTemplates.filter((item) => item.id !== template?.id),
);

function typeLabel(type: string): string {
    return props.fieldTypes.find((item) => item.value === type)?.label ?? type;
}

function describe(field: BuilderField): BuilderFieldRow {
    return {
        name: field.name,
        slug: field.slug,
        typeLabel: typeLabel(field.type),
        icon: templateFieldTypeIcon(field.type),
        required: field.is_required,
    };
}

/** Each list of fields (those with no section, then each section's) and where its errors are. */
const lists = computed(() => [
    { fields: form.fields, errorPrefix: 'fields' },
    ...form.sections.map((section, sectionIndex) => ({
        fields: section.fields,
        errorPrefix: `sections.${sectionIndex}.fields`,
    })),
]);

const fieldCount = computed(() =>
    lists.value.reduce((count, list) => count + list.fields.length, 0),
);

/** The chosen field or section, by uid. */
const selection = ref<{ kind: 'field' | 'section'; uid: number } | null>(null);

const selectedFieldUid = computed({
    get: () => (selection.value?.kind === 'field' ? selection.value.uid : null),
    set: (uid: number | null) => {
        selection.value = uid === null ? null : { kind: 'field', uid };
    },
});

/** The chosen field, the list it's in, and where. */
const selectedField = computed(() => {
    if (selection.value?.kind !== 'field') {
        return null;
    }

    for (const list of lists.value) {
        const fieldIndex = list.fields.findIndex(
            (field) => field.uid === selection.value?.uid,
        );

        if (fieldIndex !== -1) {
            return {
                list,
                index: fieldIndex,
                field: list.fields[fieldIndex],
                errorPrefix: `${list.errorPrefix}.${fieldIndex}`,
            };
        }
    }

    return null;
});

const selectedSection = computed(() => {
    if (selection.value?.kind !== 'section') {
        return null;
    }

    const sectionIndex = form.sections.findIndex(
        (section) => section.uid === selection.value?.uid,
    );

    return sectionIndex === -1
        ? null
        : { section: form.sections[sectionIndex], index: sectionIndex };
});

const isPanelOpen = computed({
    get: () => selectedField.value !== null || selectedSection.value !== null,
    set: (open: boolean) => {
        if (!open) {
            selection.value = null;
        }
    },
});

const panelTitle = computed(() => {
    if (selectedField.value) {
        return (
            selectedField.value.field.name || trans('common.builder.untitled')
        );
    }

    return (
        selectedSection.value?.section.name ||
        trans('cms.templates.builder.section_untitled')
    );
});

const panelDescription = computed(() =>
    selectedField.value
        ? trans('cms.templates.builder.field_of_type', {
              type: typeLabel(selectedField.value.field.type),
          })
        : trans('cms.templates.builder.section'),
);

/** The types offered for a new field, grouped. Types needing a module the tenant lacks aren't offered. */
const pickerGroups = computed<PickerFieldTypeGroup[]>(() =>
    templateFieldTypeGroups
        .map((group) => ({
            label: trans(`cms.templates.builder.type_groups.${group}`),
            types: allowedFieldTypes.value
                .filter(
                    (type) =>
                        !type.needsModule &&
                        (templateFieldTypeMeta[type.value]?.group ?? 'text') ===
                            group,
                )
                .map((type) => ({
                    value: type.value,
                    label: type.label,
                    description: trans(
                        `cms.templates.builder.type_descriptions.${type.value}`,
                    ),
                    icon: templateFieldTypeIcon(type.value),
                    disabled: !type.isAvailable,
                })),
        }))
        .filter((group) => group.types.length),
);

const isPickerOpen = ref(false);

function newField(type: string): BuilderField {
    return toBuilderField({
        id: null,
        type,
        name: '',
        slug: '',
        description: '',
        is_required: false,
        settings: {},
    });
}

/** Adds the field after the chosen one, at the end of the chosen section, or at the end of the fields with no section. */
function addField(type: string) {
    const field = newField(type);

    if (selectedField.value) {
        selectedField.value.list.fields.splice(
            selectedField.value.index + 1,
            0,
            field,
        );
    } else if (selectedSection.value) {
        selectedSection.value.section.fields.push(field);
    } else {
        form.fields.push(field);
    }

    selection.value = { kind: 'field', uid: field.uid };
}

function duplicateField() {
    const place = selectedField.value;

    if (!place) {
        return;
    }

    const copy: BuilderField = {
        ...JSON.parse(JSON.stringify(place.field)),
        id: null,
        uid: nextUid++,
        name: trans('cms.templates.builder.copy_name', {
            name: place.field.name,
        }),
        slug: place.field.slug ? `${place.field.slug}_copy` : '',
    };

    place.list.fields.splice(place.index + 1, 0, copy);
    selection.value = { kind: 'field', uid: copy.uid };
}

/** Removes the chosen field, choosing the next one in its list (or the one before). */
function removeField() {
    const place = selectedField.value;

    if (!place) {
        return;
    }

    place.list.fields.splice(place.index, 1);

    const next =
        place.list.fields[place.index] ?? place.list.fields[place.index - 1];
    selection.value = next ? { kind: 'field', uid: next.uid } : null;
}

function addSection() {
    const section: BuilderSection = {
        id: null,
        uid: nextUid++,
        name: '',
        description: null,
        is_collapsed: false,
        fields: [],
    };

    form.sections.push(section);
    selection.value = { kind: 'section', uid: section.uid };
}

function sectionHasError(sectionIndex: number): boolean {
    return ['name', 'description', 'is_collapsed'].some(
        (key) => errors.value[`sections.${sectionIndex}.${key}`],
    );
}

/** The section being removed while the user decides what happens to its fields. */
const removingSection = ref<BuilderSection | null>(null);

const isRemoveSectionOpen = computed({
    get: () => removingSection.value !== null,
    set: (open: boolean) => {
        if (!open) {
            removingSection.value = null;
        }
    },
});

/** A section with fields asks first whether to keep them (above the sections) or delete them. */
function removeSection(section: BuilderSection) {
    if (section.fields.length) {
        removingSection.value = section;

        return;
    }

    form.sections = form.sections.filter((item) => item !== section);
    selection.value = null;
}

function confirmRemoveSection(keepFields: boolean) {
    const section = removingSection.value;

    if (!section) {
        return;
    }

    if (keepFields) {
        form.fields.push(...section.fields);
    }

    form.sections = form.sections.filter((item) => item !== section);
    removingSection.value = null;
    selection.value = null;
}

const detailKeys = ['type', 'name', 'slug', 'description'];

const fieldErrorCount = computed(
    () =>
        Object.entries(errors.value).filter(
            ([key, message]) =>
                message &&
                (key.startsWith('fields') || key.startsWith('sections')),
        ).length,
);

const tab = ref('fields');

const tabs = computed<EditorTab[]>(() => [
    {
        value: 'fields',
        label: trans('cms.templates.builder.title'),
        count: fieldCount.value,
        errorCount: fieldErrorCount.value,
    },
    {
        value: 'details',
        label: trans('cms.templates.sections.details'),
        errorCount: detailKeys.filter((key) => errors.value[key]).length,
    },
]);

/** After a failed save, show the first section or field with errors, or else the details. */
function revealFirstError() {
    const hasErrorUnder = (prefix: string) =>
        Object.entries(errors.value).some(
            ([key, message]) => message && key.startsWith(prefix),
        );

    const firstField = form.fields.find((_, fieldIndex) =>
        hasErrorUnder(`fields.${fieldIndex}.`),
    );

    if (firstField) {
        tab.value = 'fields';
        selection.value = { kind: 'field', uid: firstField.uid };

        return;
    }

    for (const [sectionIndex, section] of form.sections.entries()) {
        if (sectionHasError(sectionIndex)) {
            tab.value = 'fields';
            selection.value = { kind: 'section', uid: section.uid };

            return;
        }

        const field = section.fields.find((_, fieldIndex) =>
            hasErrorUnder(`sections.${sectionIndex}.fields.${fieldIndex}.`),
        );

        if (field) {
            tab.value = 'fields';
            selection.value = { kind: 'field', uid: field.uid };

            return;
        }
    }

    tab.value = fieldErrorCount.value ? 'fields' : 'details';
}

function withoutUid({ uid, ...field }: BuilderField): TemplateFieldData {
    void uid;

    return field;
}

function sectionWithoutUid({ uid, fields, ...section }: BuilderSection) {
    void uid;

    return { ...section, fields: fields.map(withoutUid) };
}

function onSubmit() {
    if (props.readonly) return;

    // The client-side keys are only for reordering, so don't send them
    form.transform((data) => ({
        ...data,
        fields: data.fields.map(withoutUid),
        sections: data.sections.map(sectionWithoutUid),
    }));

    form.submit(template ? update({ template: template.id }) : store(), {
        onError: revealFirstError,
    });
}

const title = computed(() => {
    if (!template) {
        return trans('cms.templates.create.title');
    }

    return props.readonly
        ? trans('cms.templates.show.title', { name: template.name })
        : trans('cms.templates.edit.title', { name: template.name });
});

const breadcrumbs = computed(() => [
    { label: trans('cms.templates.index.title'), to: index.url() },
    { label: template?.name ?? trans('cms.templates.create.title') },
]);
</script>

<template>
    <EditorLayout
        v-model:tab="tab"
        :title="title"
        :breadcrumbs="breadcrumbs"
        :tabs="tabs"
        :is-dirty="form.isDirty"
        :processing="form.processing"
        :readonly="readonly"
        :submit-label="
            template ? undefined : trans('cms.templates.create.submit')
        "
        @submit="onSubmit"
    >
        <template v-if="template" #badges>
            <UBadge color="neutral" variant="subtle" class="shrink-0">
                {{ template.type_label }}
            </UBadge>
        </template>

        <div
            v-show="tab === 'fields'"
            class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,24rem)_minmax(0,1fr)] lg:items-start xl:grid-cols-[minmax(0,28rem)_minmax(0,1fr)]"
        >
            <section class="bg-elevated/50 ring-default rounded-lg ring">
                <div
                    class="border-default flex items-center gap-2 border-b py-3 pl-4 pr-3"
                >
                    <h2 class="text-highlighted flex-1 font-semibold">
                        {{ trans('cms.templates.builder.title') }}
                    </h2>
                    <template v-if="!readonly">
                        <UButton
                            color="neutral"
                            variant="outline"
                            size="sm"
                            icon="i-lucide-rows-3"
                            :label="trans('cms.templates.builder.add_section')"
                            @click="addSection"
                        />
                        <UButton
                            size="sm"
                            icon="i-lucide-plus"
                            :label="trans('cms.templates.builder.add_field')"
                            @click="isPickerOpen = true"
                        />
                    </template>
                </div>

                <div class="flex flex-col gap-3 p-2">
                    <p v-if="errors.fields" class="text-error px-2 text-sm">
                        {{ errors.fields }}
                    </p>

                    <BuilderFieldList
                        v-if="form.fields.length || !readonly"
                        v-model="form.fields"
                        v-model:selected="selectedFieldUid"
                        group="template-fields"
                        error-prefix="fields"
                        :describe="describe"
                        :errors="errors"
                        :empty-text="
                            form.sections.length
                                ? trans(
                                      'cms.templates.builder.empty_unsectioned',
                                  )
                                : trans('cms.templates.builder.empty')
                        "
                        :readonly="readonly"
                    />

                    <VueDraggable
                        v-model="form.sections"
                        :disabled="readonly"
                        group="template-sections"
                        handle=".section-drag-handle"
                        :animation="150"
                        class="flex flex-col gap-3"
                    >
                        <div
                            v-for="(section, sectionIndex) in form.sections"
                            :key="section.uid"
                            class="flex flex-col gap-1"
                        >
                            <div
                                class="flex items-center gap-1 rounded-md"
                                :class="
                                    selection?.kind === 'section' &&
                                    selection.uid === section.uid
                                        ? 'bg-primary/10 ring-primary/40 ring'
                                        : 'hover:bg-elevated'
                                "
                            >
                                <UButton
                                    v-if="!readonly"
                                    class="section-drag-handle cursor-grab"
                                    color="neutral"
                                    variant="link"
                                    size="sm"
                                    icon="i-lucide-grip-vertical"
                                    :aria-label="
                                        trans(
                                            'cms.templates.builder.section_drag',
                                        )
                                    "
                                />
                                <button
                                    type="button"
                                    class="focus-visible:outline-primary flex min-w-0 flex-1 items-center gap-2 rounded-md py-2 pr-2 text-left focus-visible:outline-2"
                                    :class="{ 'pl-2': readonly }"
                                    :aria-pressed="
                                        selection?.kind === 'section' &&
                                        selection.uid === section.uid
                                    "
                                    @click="
                                        selection = {
                                            kind: 'section',
                                            uid: section.uid,
                                        }
                                    "
                                >
                                    <UIcon
                                        name="i-lucide-rows-3"
                                        class="text-muted size-4 shrink-0"
                                    />
                                    <span
                                        class="text-toned min-w-0 flex-1 truncate text-xs font-semibold uppercase tracking-wide"
                                    >
                                        {{
                                            section.name ||
                                            trans(
                                                'cms.templates.builder.section_untitled',
                                            )
                                        }}
                                    </span>
                                    <span class="text-muted shrink-0 text-xs">
                                        {{
                                            trans(
                                                'cms.templates.builder.section',
                                            )
                                        }}
                                    </span>
                                    <span
                                        v-if="sectionHasError(sectionIndex)"
                                        class="bg-error size-2 shrink-0 rounded-full"
                                        role="img"
                                        :aria-label="
                                            trans('common.builder.has_errors')
                                        "
                                    />
                                </button>
                            </div>

                            <div class="pl-4">
                                <BuilderFieldList
                                    v-model="section.fields"
                                    v-model:selected="selectedFieldUid"
                                    group="template-fields"
                                    :error-prefix="`sections.${sectionIndex}.fields`"
                                    :describe="describe"
                                    :errors="errors"
                                    :error="
                                        errors[
                                            `sections.${sectionIndex}.fields`
                                        ]
                                    "
                                    :empty-text="
                                        trans(
                                            'cms.templates.builder.section_empty',
                                        )
                                    "
                                    :readonly="readonly"
                                />
                            </div>
                        </div>
                    </VueDraggable>
                </div>
            </section>

            <BuilderFieldPanel
                v-model:open="isPanelOpen"
                :title="panelTitle"
                :description="panelDescription"
                :empty-text="trans('cms.templates.builder.choose_field')"
            >
                <template v-if="!readonly" #actions>
                    <template v-if="selectedField">
                        <UButton
                            color="neutral"
                            variant="ghost"
                            icon="i-lucide-copy"
                            :aria-label="
                                trans('cms.templates.builder.duplicate')
                            "
                            @click="duplicateField"
                        />
                        <UButton
                            color="error"
                            variant="ghost"
                            icon="i-lucide-trash"
                            :aria-label="trans('cms.templates.builder.remove')"
                            @click="removeField"
                        />
                    </template>
                    <UButton
                        v-else-if="selectedSection"
                        color="error"
                        variant="ghost"
                        icon="i-lucide-trash"
                        :aria-label="
                            trans('cms.templates.builder.section_remove')
                        "
                        @click="removeSection(selectedSection.section)"
                    />
                </template>

                <TemplateFieldEditor
                    v-if="selectedField"
                    :key="selectedField.field.uid"
                    v-model="selectedField.list.fields[selectedField.index]"
                    :error-prefix="selectedField.errorPrefix"
                    :field-types="allowedFieldTypes"
                    :nested-templates="nestedTemplateOptions"
                    :organisation-unit-types="organisationUnitTypes"
                    :errors="errors"
                    :readonly="readonly"
                />

                <div
                    v-else-if="selectedSection"
                    :key="selectedSection.section.uid"
                    class="flex flex-col gap-4"
                >
                    <UFormField
                        :label="trans('cms.templates.builder.section_name')"
                        :error="
                            errors[`sections.${selectedSection.index}.name`]
                        "
                        required
                    >
                        <UInput
                            v-model="selectedSection.section.name"
                            :disabled="readonly"
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        :label="
                            trans('cms.templates.builder.section_description')
                        "
                        :help="
                            readonly
                                ? undefined
                                : trans(
                                      'cms.templates.builder.section_description_help',
                                  )
                        "
                        :error="
                            errors[
                                `sections.${selectedSection.index}.description`
                            ]
                        "
                    >
                        <UInput
                            v-model.nullable="
                                selectedSection.section.description
                            "
                            :disabled="readonly"
                            class="w-full"
                        />
                    </UFormField>

                    <div
                        class="border-default flex items-center justify-between gap-3 rounded-md border p-3"
                    >
                        <div>
                            <p class="text-highlighted text-sm font-medium">
                                {{
                                    trans(
                                        'cms.templates.builder.section_collapsed',
                                    )
                                }}
                            </p>
                            <p class="text-muted text-sm">
                                {{
                                    trans(
                                        'cms.templates.builder.section_collapsed_help',
                                    )
                                }}
                            </p>
                        </div>
                        <USwitch
                            v-model="selectedSection.section.is_collapsed"
                            :disabled="readonly"
                            :aria-label="
                                trans('cms.templates.builder.section_collapsed')
                            "
                        />
                    </div>
                </div>
            </BuilderFieldPanel>
        </div>

        <UPageCard
            v-show="tab === 'details'"
            variant="subtle"
            :title="trans('cms.templates.sections.details')"
        >
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <UFormField
                    :label="trans('cms.templates.fields.type')"
                    :help="
                        readonly
                            ? undefined
                            : isInUse
                              ? trans('cms.templates.fields.type_in_use')
                              : trans('cms.templates.fields.type_help')
                    "
                    :error="form.errors.type"
                    required
                >
                    <USelect
                        v-model="form.type"
                        :items="types"
                        :disabled="readonly || isInUse"
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    :label="trans('cms.templates.fields.name')"
                    :error="form.errors.name"
                    required
                >
                    <UInput
                        v-model="form.name"
                        :disabled="readonly"
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    :label="trans('cms.templates.fields.slug')"
                    :help="
                        readonly
                            ? undefined
                            : trans('cms.templates.fields.slug_help')
                    "
                    :error="form.errors.slug"
                >
                    <UInput
                        v-model="form.slug"
                        :disabled="readonly"
                        class="w-full font-mono"
                    />
                </UFormField>

                <UFormField
                    class="md:col-span-2"
                    :label="trans('cms.templates.fields.description')"
                    :error="form.errors.description"
                >
                    <UTextarea
                        v-model="form.description"
                        :disabled="readonly"
                        :rows="2"
                        class="w-full"
                    />
                </UFormField>
            </div>
        </UPageCard>

        <FieldTypePicker
            v-model:open="isPickerOpen"
            :groups="pickerGroups"
            @select="addField"
        />

        <UModal
            v-model:open="isRemoveSectionOpen"
            :title="
                trans('cms.templates.builder.section_remove_title', {
                    name:
                        removingSection?.name ||
                        trans('cms.templates.builder.section_untitled'),
                })
            "
            :description="
                trans('cms.templates.builder.section_remove_description')
            "
        >
            <template #body>
                <div class="flex flex-wrap justify-end gap-2">
                    <UButton
                        color="neutral"
                        variant="ghost"
                        :label="trans('common.actions.cancel')"
                        @click="removingSection = null"
                    />
                    <UButton
                        color="neutral"
                        variant="subtle"
                        :label="
                            trans('cms.templates.builder.section_keep_fields')
                        "
                        @click="confirmRemoveSection(true)"
                    />
                    <UButton
                        color="error"
                        :label="
                            trans('cms.templates.builder.section_delete_fields')
                        "
                        @click="confirmRemoveSection(false)"
                    />
                </div>
            </template>
        </UModal>
    </EditorLayout>
</template>
