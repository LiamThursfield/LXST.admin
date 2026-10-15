<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { VueDraggable } from 'vue-draggable-plus';
import * as z from 'zod';
import {
    store,
    update,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Crm/FormController';
import type { CrmFormData, FormFieldData, FormFieldTypeOption } from '@/types';
import FormFieldEditor from './FormFieldEditor.vue';

const props = withDefaults(
    defineProps<{
        crmForm?: CrmFormData;
        fieldTypes: FormFieldTypeOption[];
        readonly?: boolean;
        create?: boolean;
    }>(),
    {
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

const schema = z.object({
    name: z
        .string()
        .min(1, trans('crm.forms.validation.name_required'))
        .max(120),
});

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

function addField() {
    form.fields.push(
        toBuilderField({
            id: null,
            type: 'text',
            name: '',
            slug: '',
            is_required: false,
            settings: {},
        }),
    );
}

function removeField(index: number) {
    form.fields.splice(index, 1);
}

function withoutUid({ uid, ...field }: BuilderField): FormFieldData {
    void uid;

    return field;
}

function addRecipient() {
    form.email_recipients.push('');
}

function removeRecipient(index: number) {
    form.email_recipients.splice(index, 1);
}

function onSubmit() {
    if (props.readonly) return;

    // The client-side keys are only for reordering, so don't send them
    form.transform((data) => ({
        ...data,
        fields: data.fields.map(withoutUid),
    }));

    if (props.create) {
        form.submit(store());
        return;
    }

    form.submit(update({ form: crmForm!.id }));
}
</script>

<template>
    <UForm :schema="schema" :state="form" class="space-y-6" @submit="onSubmit">
        <UPageCard
            variant="subtle"
            :title="trans('crm.forms.sections.details')"
        >
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <UFormField
                    :label="trans('crm.forms.fields.name')"
                    name="name"
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
                    name="slug"
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
                    name="submit_button_text"
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
                            : trans('crm.forms.fields.email_recipients_help')
                    "
                    name="email_recipients"
                    :error="form.errors.email_recipients"
                >
                    <div class="flex flex-col gap-2">
                        <div
                            v-for="(_, index) in form.email_recipients"
                            :key="index"
                            class="flex flex-col gap-1"
                        >
                            <div class="flex gap-2">
                                <UInput
                                    v-model="form.email_recipients[index]"
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
                                    @click="removeRecipient(index)"
                                />
                            </div>
                            <p
                                v-if="errors[`email_recipients.${index}`]"
                                class="text-error text-sm"
                            >
                                {{ errors[`email_recipients.${index}`] }}
                            </p>
                        </div>

                        <UButton
                            v-if="!readonly"
                            class="w-fit"
                            color="neutral"
                            variant="subtle"
                            icon="i-lucide-plus"
                            :label="
                                trans('crm.forms.fields.add_email_recipient')
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
                    :name="field"
                />
            </div>
        </UPageCard>

        <UPageCard
            variant="subtle"
            :title="trans('crm.forms.builder.title')"
            :description="
                readonly ? undefined : trans('crm.forms.builder.description')
            "
        >
            <p v-if="errors.fields" class="text-error text-sm">
                {{ errors.fields }}
            </p>

            <p v-if="!form.fields.length" class="text-muted text-sm">
                {{ trans('crm.forms.builder.empty') }}
            </p>

            <VueDraggable
                v-model="form.fields"
                :disabled="readonly"
                handle=".drag-handle"
                :animation="150"
                class="flex flex-col gap-3"
            >
                <FormFieldEditor
                    v-for="(field, index) in form.fields"
                    :key="field.uid"
                    v-model="form.fields[index]"
                    :index="index"
                    :field-types="fieldTypes"
                    :errors="errors"
                    :readonly="readonly"
                    @remove="removeField(index)"
                />
            </VueDraggable>

            <template v-if="!readonly" #footer>
                <UButton
                    color="neutral"
                    variant="subtle"
                    icon="i-lucide-plus"
                    :label="trans('crm.forms.builder.add')"
                    @click="addField"
                />
            </template>
        </UPageCard>

        <UPageCard
            variant="subtle"
            :title="trans('crm.forms.sections.after_submit')"
        >
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <UFormField
                    class="md:col-span-2"
                    :label="trans('crm.forms.fields.success_message')"
                    :help="
                        readonly
                            ? undefined
                            : trans('crm.forms.fields.success_message_help')
                    "
                    name="success_message"
                    :error="form.errors.success_message"
                >
                    <UTextarea
                        v-model="form.success_message"
                        :disabled="readonly"
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    class="md:col-span-2"
                    :label="trans('crm.forms.fields.redirect_url')"
                    :help="
                        readonly
                            ? undefined
                            : trans('crm.forms.fields.redirect_url_help')
                    "
                    name="redirect_url"
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

        <div v-if="!readonly" class="flex justify-end gap-2">
            <UButton
                :loading="form.processing"
                color="primary"
                :label="
                    create
                        ? trans('crm.forms.create.submit')
                        : trans('crm.save_changes')
                "
                type="submit"
            />
        </div>
    </UForm>
</template>
