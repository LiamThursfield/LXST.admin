<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { VueDraggable } from 'vue-draggable-plus';
import {
    store,
    update,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Cms/TemplateController';
import BuilderFieldList from '@/tenant/components/builder/BuilderFieldList.vue';
import type {
    NestedTemplateOption,
    SelectItem,
    TemplateData,
    TemplateFieldData,
    TemplateFieldTypeOption,
    TemplateSectionData,
    TemplateType,
} from '@/types';
import TemplateFieldEditor from './TemplateFieldEditor.vue';

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

function newField(): BuilderField {
    return toBuilderField({
        id: null,
        type:
            allowedFieldTypes.value.find(
                (type) => type.isAvailable && !type.needsModule,
            )?.value ?? 'text',
        name: '',
        slug: '',
        description: '',
        is_required: false,
        settings: {},
    });
}

function addSection() {
    form.sections.push({
        id: null,
        uid: nextUid++,
        name: '',
        description: null,
        is_collapsed: false,
        fields: [],
    });
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

    form.submit(template ? update({ template: template.id }) : store());
}
</script>

<template>
    <form class="space-y-6" @submit.prevent="onSubmit">
        <UPageCard
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

        <UPageCard
            variant="subtle"
            :title="trans('cms.templates.builder.title')"
            :description="
                readonly ? undefined : trans('cms.templates.builder.intro')
            "
        >
            <div class="flex flex-col gap-6">
                <BuilderFieldList
                    v-model="form.fields"
                    group="template-fields"
                    :empty-text="
                        form.sections.length
                            ? trans('cms.templates.builder.empty_unsectioned')
                            : trans('cms.templates.builder.empty')
                    "
                    :add-label="trans('cms.templates.builder.add')"
                    :error="errors.fields"
                    :readonly="readonly"
                    @add="form.fields.push(newField())"
                >
                    <template #default="{ index }">
                        <TemplateFieldEditor
                            v-model="form.fields[index]"
                            :error-prefix="`fields.${index}`"
                            :field-types="allowedFieldTypes"
                            :nested-templates="nestedTemplateOptions"
                            :organisation-unit-types="organisationUnitTypes"
                            :errors="errors"
                            :readonly="readonly"
                            @remove="form.fields.splice(index, 1)"
                        />
                    </template>
                </BuilderFieldList>

                <VueDraggable
                    v-model="form.sections"
                    :disabled="readonly"
                    group="template-sections"
                    handle=".section-drag-handle"
                    :animation="150"
                    class="flex flex-col gap-6"
                >
                    <UCard
                        v-for="(section, sectionIndex) in form.sections"
                        :key="section.uid"
                        variant="subtle"
                        :ui="{ header: 'p-4 sm:p-4', body: 'p-4 sm:p-4' }"
                    >
                        <template #header>
                            <div class="flex gap-3">
                                <UButton
                                    v-if="!readonly"
                                    class="section-drag-handle cursor-grab self-start"
                                    color="neutral"
                                    variant="ghost"
                                    icon="i-lucide-grip-vertical"
                                    :aria-label="
                                        trans(
                                            'cms.templates.builder.section_drag',
                                        )
                                    "
                                />

                                <div
                                    class="grid flex-1 grid-cols-1 gap-4 md:grid-cols-2"
                                >
                                    <UFormField
                                        :label="
                                            trans(
                                                'cms.templates.builder.section_name',
                                            )
                                        "
                                        :error="
                                            errors[
                                                `sections.${sectionIndex}.name`
                                            ]
                                        "
                                        required
                                    >
                                        <UInput
                                            v-model="section.name"
                                            :disabled="readonly"
                                            class="w-full"
                                        />
                                    </UFormField>

                                    <UFormField
                                        :label="
                                            trans(
                                                'cms.templates.builder.section_description',
                                            )
                                        "
                                        :error="
                                            errors[
                                                `sections.${sectionIndex}.description`
                                            ]
                                        "
                                    >
                                        <UInput
                                            v-model.nullable="
                                                section.description
                                            "
                                            :disabled="readonly"
                                            class="w-full"
                                        />
                                    </UFormField>

                                    <UFormField
                                        class="md:col-span-2"
                                        :help="
                                            readonly
                                                ? undefined
                                                : trans(
                                                      'cms.templates.builder.section_collapsed_help',
                                                  )
                                        "
                                    >
                                        <USwitch
                                            v-model="section.is_collapsed"
                                            :disabled="readonly"
                                            :label="
                                                trans(
                                                    'cms.templates.builder.section_collapsed',
                                                )
                                            "
                                        />
                                    </UFormField>
                                </div>

                                <UButton
                                    v-if="!readonly"
                                    class="self-start"
                                    color="neutral"
                                    variant="ghost"
                                    icon="i-lucide-trash"
                                    :aria-label="
                                        trans(
                                            'cms.templates.builder.section_remove',
                                        )
                                    "
                                    @click="removeSection(section)"
                                />
                            </div>
                        </template>

                        <BuilderFieldList
                            v-model="section.fields"
                            group="template-fields"
                            :empty-text="
                                trans('cms.templates.builder.section_empty')
                            "
                            :add-label="trans('cms.templates.builder.add')"
                            :error="errors[`sections.${sectionIndex}.fields`]"
                            :readonly="readonly"
                            @add="section.fields.push(newField())"
                        >
                            <template #default="{ index }">
                                <TemplateFieldEditor
                                    v-model="section.fields[index]"
                                    :error-prefix="`sections.${sectionIndex}.fields.${index}`"
                                    :field-types="allowedFieldTypes"
                                    :nested-templates="nestedTemplateOptions"
                                    :organisation-unit-types="
                                        organisationUnitTypes
                                    "
                                    :errors="errors"
                                    :readonly="readonly"
                                    @remove="section.fields.splice(index, 1)"
                                />
                            </template>
                        </BuilderFieldList>
                    </UCard>
                </VueDraggable>

                <UButton
                    v-if="!readonly"
                    class="w-fit"
                    color="neutral"
                    variant="outline"
                    icon="i-lucide-rows-3"
                    :label="trans('cms.templates.builder.section_add')"
                    @click="addSection"
                />
            </div>
        </UPageCard>

        <div v-if="!readonly" class="flex justify-end gap-2">
            <UButton
                type="submit"
                color="primary"
                :loading="form.processing"
                :label="
                    template
                        ? trans('cms.templates.edit.submit')
                        : trans('cms.templates.create.submit')
                "
            />
        </div>

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
    </form>
</template>
