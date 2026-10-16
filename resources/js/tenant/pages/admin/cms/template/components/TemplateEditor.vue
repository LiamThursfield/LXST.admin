<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import {
    store,
    update,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Cms/TemplateController';
import BuilderFieldList from '@/tenant/components/builder/BuilderFieldList.vue';
import type {
    SelectItem,
    TemplateData,
    TemplateFieldData,
    TemplateFieldTypeOption,
    TemplateType,
} from '@/types';
import TemplateFieldEditor from './TemplateFieldEditor.vue';

const props = withDefaults(
    defineProps<{
        template?: TemplateData;
        types: SelectItem[];
        fieldTypes: TemplateFieldTypeOption[];
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

let nextUid = 0;

function toBuilderField(field: TemplateFieldData): BuilderField {
    return { ...field, settings: { ...field.settings }, uid: nextUid++ };
}

const template = props.template;

const form = useForm({
    type: (template?.type ?? 'page') as TemplateType,
    name: template?.name ?? '',
    slug: template?.slug ?? '',
    description: template?.description ?? '',
    fields: (template?.fields ?? []).map(toBuilderField) as BuilderField[],
});

/** Server validation errors, including nested ones such as `fields.0.name`. */
const errors = computed(
    () => form.errors as Record<string, string | undefined>,
);

/** The field types this type of template allows. */
const allowedFieldTypes = computed(() =>
    props.fieldTypes.filter((type) => type.templateTypes.includes(form.type)),
);

function addField() {
    form.fields.push(
        toBuilderField({
            id: null,
            type:
                allowedFieldTypes.value.find((type) => type.isAvailable)
                    ?.value ?? 'text',
            name: '',
            slug: '',
            description: '',
            is_required: false,
            settings: {},
        }),
    );
}

function removeField(index: number) {
    form.fields.splice(index, 1);
}

function withoutUid({ uid, ...field }: BuilderField): TemplateFieldData {
    void uid;

    return field;
}

function onSubmit() {
    if (props.readonly) return;

    // The client-side keys are only for reordering, so don't send them
    form.transform((data) => ({
        ...data,
        fields: data.fields.map(withoutUid),
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
                readonly
                    ? undefined
                    : trans('cms.templates.builder.intro')
            "
        >
            <BuilderFieldList
                v-model="form.fields"
                :empty-text="trans('cms.templates.builder.empty')"
                :add-label="trans('cms.templates.builder.add')"
                :error="errors.fields"
                :readonly="readonly"
                @add="addField"
            >
                <template #default="{ index }">
                    <TemplateFieldEditor
                        v-model="form.fields[index]"
                        :index="index"
                        :field-types="allowedFieldTypes"
                        :errors="errors"
                        :readonly="readonly"
                        @remove="removeField(index)"
                    />
                </template>
            </BuilderFieldList>
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
    </form>
</template>
