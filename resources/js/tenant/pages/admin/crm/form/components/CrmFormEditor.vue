<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    index,
    store,
    update,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Crm/FormController';
import type { EditorTab } from '@/components/EditorLayout.vue';
import BuilderFieldList from '@/tenant/components/builder/BuilderFieldList.vue';
import type { BuilderFieldRow } from '@/tenant/components/builder/BuilderFieldList.vue';
import BuilderFieldPanel from '@/tenant/components/builder/BuilderFieldPanel.vue';
import FieldTypePicker from '@/tenant/components/builder/FieldTypePicker.vue';
import type { PickerFieldTypeGroup } from '@/tenant/components/builder/FieldTypePicker.vue';
import type { CrmFormData, FormFieldData, FormFieldTypeOption } from '@/types';
import { formFieldTypeIcon } from './fieldTypes';
import FormFieldEditor from './FormFieldEditor.vue';

/**
 * Creates, edits or shows a CRM form. The Fields tab lists the fields compactly beside the
 * settings of the one chosen; the Details tab has the form's details, marketing consent and what
 * happens after submitting.
 */
const props = withDefaults(
    defineProps<{
        crmForm?: CrmFormData;
        fieldTypes: FormFieldTypeOption[];
        readonly?: boolean;
        create?: boolean;
    }>(),
    {
        crmForm: undefined,
        readonly: false,
        create: false,
    },
);

const { trans } = useTrans();

/** A field in the builder, with a client-side key so the list can be reordered. */
type BuilderField = FormFieldData & { uid: number };

let nextUid = 0;

function toBuilderField(field: FormFieldData): BuilderField {
    return { ...field, settings: { ...field.settings }, uid: nextUid++ };
}

const crmForm = props.crmForm;

const form = useForm({
    name: crmForm?.name ?? '',
    slug: crmForm?.slug ?? '',
    email_recipients: [...(crmForm?.email_recipients ?? [])],
    marketing_email: crmForm?.marketing_email ?? false,
    marketing_sms: crmForm?.marketing_sms ?? false,
    marketing_telephone: crmForm?.marketing_telephone ?? false,
    submit_button_text: crmForm?.submit_button_text ?? '',
    redirect_url: crmForm?.redirect_url ?? '',
    success_message: crmForm?.success_message ?? '',
    fields: (crmForm?.fields ?? []).map(toBuilderField) as BuilderField[],
});

const marketingFields = [
    'marketing_email',
    'marketing_sms',
    'marketing_telephone',
] as const;

/** Server validation errors, including nested ones such as `fields.0.name`. */
const errors = computed(
    () => form.errors as Record<string, string | undefined>,
);

function typeLabel(type: string): string {
    return props.fieldTypes.find((item) => item.value === type)?.label ?? type;
}

function describe(field: BuilderField): BuilderFieldRow {
    return {
        name: field.name,
        slug: field.slug,
        typeLabel: typeLabel(field.type),
        icon: formFieldTypeIcon(field.type),
        required: field.is_required,
    };
}

/** The chosen field's uid. */
const selectedUid = ref<number | null>(null);

const selectedIndex = computed(() =>
    form.fields.findIndex((field) => field.uid === selectedUid.value),
);

const selectedField = computed(() =>
    selectedIndex.value === -1 ? null : form.fields[selectedIndex.value],
);

const isPanelOpen = computed({
    get: () => selectedField.value !== null,
    set: (open: boolean) => {
        if (!open) {
            selectedUid.value = null;
        }
    },
});

/** The types offered for a new field: standard fields, then those filling in the contact's details. */
const pickerGroups = computed<PickerFieldTypeGroup[]>(() =>
    [false, true]
        .map((isContactField) => ({
            label: isContactField
                ? trans('crm.forms.builder.type_groups.contact')
                : trans('crm.forms.builder.type_groups.standard'),
            types: props.fieldTypes
                .filter((type) => type.isContactField === isContactField)
                .map((type) => ({
                    value: type.value,
                    label: type.label,
                    description: trans(
                        `crm.forms.builder.type_descriptions.${type.value}`,
                    ),
                    icon: formFieldTypeIcon(type.value),
                })),
        }))
        .filter((group) => group.types.length),
);

const isPickerOpen = ref(false);

/** Adds the field after the chosen one, or at the end, and chooses it. */
function addField(type: string) {
    const field = toBuilderField({
        id: null,
        type,
        name: '',
        slug: '',
        is_required: false,
        settings: {},
    });

    form.fields.splice(
        selectedIndex.value === -1
            ? form.fields.length
            : selectedIndex.value + 1,
        0,
        field,
    );
    selectedUid.value = field.uid;
}

function duplicateField() {
    const field = selectedField.value;

    if (!field) {
        return;
    }

    const copy: BuilderField = {
        ...JSON.parse(JSON.stringify(field)),
        id: null,
        uid: nextUid++,
        name: trans('crm.forms.builder.copy_name', { name: field.name }),
        slug: field.slug ? `${field.slug}_copy` : '',
    };

    form.fields.splice(selectedIndex.value + 1, 0, copy);
    selectedUid.value = copy.uid;
}

/** Removes the chosen field, choosing the next one (or the one before). */
function removeField() {
    const fieldIndex = selectedIndex.value;

    if (fieldIndex === -1) {
        return;
    }

    form.fields.splice(fieldIndex, 1);

    const next = form.fields[fieldIndex] ?? form.fields[fieldIndex - 1];
    selectedUid.value = next?.uid ?? null;
}

function addRecipient() {
    form.email_recipients.push('');
}

function removeRecipient(recipientIndex: number) {
    form.email_recipients.splice(recipientIndex, 1);
}

const fieldErrorCount = computed(
    () =>
        Object.entries(errors.value).filter(
            ([key, message]) => message && key.startsWith('fields'),
        ).length,
);

const tab = ref('fields');

const tabs = computed<EditorTab[]>(() => [
    {
        value: 'fields',
        label: trans('crm.forms.builder.title'),
        count: form.fields.length,
        errorCount: fieldErrorCount.value,
    },
    {
        value: 'details',
        label: trans('crm.forms.sections.details'),
        errorCount: Object.entries(errors.value).filter(
            ([key, message]) => message && !key.startsWith('fields'),
        ).length,
    },
]);

/** After a failed save, show the first field with errors, or else the details. */
function revealFirstError() {
    const field = form.fields.find((_, fieldIndex) =>
        Object.entries(errors.value).some(
            ([key, message]) =>
                message && key.startsWith(`fields.${fieldIndex}.`),
        ),
    );

    if (field) {
        selectedUid.value = field.uid;
    }

    tab.value = fieldErrorCount.value ? 'fields' : 'details';
}

function withoutUid({ uid, ...field }: BuilderField): FormFieldData {
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

    form.submit(props.create ? store() : update({ form: crmForm!.id }), {
        onError: revealFirstError,
    });
}

const title = computed(() => {
    if (props.create || !crmForm) {
        return trans('crm.forms.create.title');
    }

    return props.readonly
        ? trans('crm.forms.show.title', { name: crmForm.name })
        : trans('crm.forms.edit.title', { name: crmForm.name });
});

const breadcrumbs = computed(() => [
    { label: trans('crm.forms.index.title'), to: index.url() },
    { label: crmForm?.name ?? trans('crm.forms.create.title') },
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
        :submit-label="create ? trans('crm.forms.create.submit') : undefined"
        @submit="onSubmit"
    >
        <div
            v-show="tab === 'fields'"
            class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,24rem)_minmax(0,1fr)] lg:items-start xl:grid-cols-[minmax(0,28rem)_minmax(0,1fr)]"
        >
            <section class="bg-elevated/50 ring-default rounded-lg ring">
                <div
                    class="border-default flex items-center gap-2 border-b py-3 pl-4 pr-3"
                >
                    <div class="min-w-0 flex-1">
                        <h2 class="text-highlighted font-semibold">
                            {{ trans('crm.forms.builder.title') }}
                        </h2>
                        <p v-if="!readonly" class="text-muted text-sm">
                            {{ trans('crm.forms.builder.description') }}
                        </p>
                    </div>
                    <UButton
                        v-if="!readonly"
                        size="sm"
                        icon="i-lucide-plus"
                        :label="trans('crm.forms.builder.add_field')"
                        @click="isPickerOpen = true"
                    />
                </div>

                <div class="p-2">
                    <BuilderFieldList
                        v-model="form.fields"
                        v-model:selected="selectedUid"
                        error-prefix="fields"
                        :describe="describe"
                        :errors="errors"
                        :error="errors.fields"
                        :empty-text="trans('crm.forms.builder.empty')"
                        :readonly="readonly"
                    />
                </div>
            </section>

            <BuilderFieldPanel
                v-model:open="isPanelOpen"
                :title="
                    selectedField?.name || trans('crm.forms.builder.untitled')
                "
                :description="
                    selectedField
                        ? trans('crm.forms.builder.field_of_type', {
                              type: typeLabel(selectedField.type),
                          })
                        : null
                "
                :empty-text="trans('crm.forms.builder.choose_field')"
            >
                <template v-if="!readonly && selectedField" #actions>
                    <UButton
                        color="neutral"
                        variant="ghost"
                        icon="i-lucide-copy"
                        :aria-label="trans('crm.forms.builder.duplicate')"
                        @click="duplicateField"
                    />
                    <UButton
                        color="error"
                        variant="ghost"
                        icon="i-lucide-trash"
                        :aria-label="trans('crm.forms.builder.remove')"
                        @click="removeField"
                    />
                </template>

                <FormFieldEditor
                    v-if="selectedField"
                    :key="selectedField.uid"
                    v-model="form.fields[selectedIndex]"
                    :error-prefix="`fields.${selectedIndex}`"
                    :field-types="fieldTypes"
                    :errors="errors"
                    :readonly="readonly"
                />
            </BuilderFieldPanel>
        </div>

        <div v-show="tab === 'details'" class="flex flex-col gap-6">
            <UPageCard
                variant="subtle"
                :title="trans('crm.forms.sections.details')"
            >
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <UFormField
                        :label="trans('crm.forms.fields.name')"
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
                        :label="trans('crm.forms.fields.slug')"
                        :help="
                            readonly
                                ? undefined
                                : trans('crm.forms.fields.slug_help')
                        "
                        :error="form.errors.slug"
                    >
                        <UInput
                            v-model="form.slug"
                            :disabled="readonly"
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        :label="trans('crm.forms.fields.submit_button_text')"
                        :error="form.errors.submit_button_text"
                    >
                        <UInput
                            v-model="form.submit_button_text"
                            :disabled="readonly"
                            :placeholder="
                                trans(
                                    'crm.forms.fields.submit_button_text_placeholder',
                                )
                            "
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        class="md:col-span-2"
                        :label="trans('crm.forms.fields.email_recipients')"
                        :help="
                            readonly
                                ? undefined
                                : trans(
                                      'crm.forms.fields.email_recipients_help',
                                  )
                        "
                        :error="form.errors.email_recipients"
                    >
                        <div class="flex flex-col gap-2">
                            <div
                                v-for="(
                                    _, recipientIndex
                                ) in form.email_recipients"
                                :key="recipientIndex"
                                class="flex flex-col gap-1"
                            >
                                <div class="flex gap-2">
                                    <UInput
                                        v-model="
                                            form.email_recipients[
                                                recipientIndex
                                            ]
                                        "
                                        :disabled="readonly"
                                        :placeholder="
                                            trans(
                                                'crm.forms.fields.email_recipient_placeholder',
                                            )
                                        "
                                        class="w-full"
                                        type="email"
                                    />
                                    <UButton
                                        v-if="!readonly"
                                        color="neutral"
                                        variant="ghost"
                                        icon="i-lucide-x"
                                        :aria-label="
                                            trans(
                                                'crm.forms.fields.remove_email_recipient',
                                            )
                                        "
                                        @click="removeRecipient(recipientIndex)"
                                    />
                                </div>
                                <p
                                    v-if="
                                        errors[
                                            `email_recipients.${recipientIndex}`
                                        ]
                                    "
                                    class="text-error text-sm"
                                >
                                    {{
                                        errors[
                                            `email_recipients.${recipientIndex}`
                                        ]
                                    }}
                                </p>
                            </div>

                            <UButton
                                v-if="!readonly"
                                class="w-fit"
                                color="neutral"
                                variant="subtle"
                                icon="i-lucide-plus"
                                :label="
                                    trans(
                                        'crm.forms.fields.add_email_recipient',
                                    )
                                "
                                @click="addRecipient"
                            />
                        </div>
                    </UFormField>
                </div>
            </UPageCard>

            <UPageCard
                variant="subtle"
                :title="trans('crm.forms.marketing.title')"
                :description="trans('crm.forms.marketing.description')"
            >
                <div class="flex flex-wrap gap-6">
                    <UCheckbox
                        v-for="field in marketingFields"
                        :key="field"
                        v-model="form[field]"
                        :disabled="readonly"
                        :label="trans(`crm.forms.marketing.${field}`)"
                    />
                </div>
            </UPageCard>

            <UPageCard
                variant="subtle"
                :title="trans('crm.forms.sections.after_submit')"
            >
                <div class="grid grid-cols-1 gap-4">
                    <UFormField
                        :label="trans('crm.forms.fields.success_message')"
                        :help="
                            readonly
                                ? undefined
                                : trans('crm.forms.fields.success_message_help')
                        "
                        :error="form.errors.success_message"
                    >
                        <UTextarea
                            v-model="form.success_message"
                            :disabled="readonly"
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        :label="trans('crm.forms.fields.redirect_url')"
                        :help="
                            readonly
                                ? undefined
                                : trans('crm.forms.fields.redirect_url_help')
                        "
                        :error="form.errors.redirect_url"
                    >
                        <UInput
                            v-model="form.redirect_url"
                            :disabled="readonly"
                            class="w-full"
                            type="url"
                        />
                    </UFormField>
                </div>
            </UPageCard>
        </div>

        <FieldTypePicker
            v-model:open="isPickerOpen"
            :groups="pickerGroups"
            @select="addField"
        />
    </EditorLayout>
</template>
