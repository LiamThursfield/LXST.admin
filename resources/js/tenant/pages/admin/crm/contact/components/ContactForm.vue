<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import * as z from 'zod';
import {
    store,
    update,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Crm/ContactController';
import AddressFields from '@/tenant/components/crm/AddressFields.vue';
import type { AddressData, ContactData } from '@/types';

const props = withDefaults(
    defineProps<{
        contact?: ContactData;
        readonly?: boolean;
        create?: boolean;
    }>(),
    {
        readonly: false,
        create: false,
    },
);

const { trans } = useTrans();

const schema = z.object({
    first_name: z
        .string()
        .min(1, trans('crm.contacts.validation.first_name_required'))
        .max(255),
    last_name: z
        .string()
        .min(1, trans('crm.contacts.validation.last_name_required'))
        .max(255),
    email: z.email(trans('crm.contacts.validation.email_invalid')).max(255),
});

const emptyAddress = (): AddressData => ({
    postcode: '',
    line_1: '',
    line_2: null,
    line_3: null,
    town_city: null,
    county: null,
    country: null,
});

const contact = props.contact;

const form = useForm({
    title: contact?.title ?? '',
    first_name: contact?.first_name ?? '',
    last_name: contact?.last_name ?? '',
    email: contact?.email ?? '',
    telephone: contact?.telephone ?? '',
    is_spam: contact?.is_spam ?? false,
    marketing_email: contact?.marketing_email ?? false,
    marketing_sms: contact?.marketing_sms ?? false,
    marketing_telephone: contact?.marketing_telephone ?? false,
    // Any address ids are ignored: the server replaces the contact's addresses with this list
    addresses: [...(contact?.addresses ?? [])] as AddressData[],
});

const marketingFields = [
    'marketing_email',
    'marketing_sms',
    'marketing_telephone',
] as const;

/** Server validation errors, including nested ones such as `addresses.0.postcode`. */
const errors = computed(
    () => form.errors as Record<string, string | undefined>,
);

function addAddress() {
    form.addresses.push(emptyAddress());
}

function removeAddress(index: number) {
    form.addresses.splice(index, 1);
}

function onSubmit() {
    if (props.readonly) return;

    if (props.create) {
        form.submit(store());
        return;
    }

    form.submit(update({ contact: contact!.id }));
}
</script>

<template>
    <UForm :schema="schema" :state="form" class="space-y-6" @submit="onSubmit">
        <UPageCard variant="subtle">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-6">
                <UFormField
                    class="md:col-span-1"
                    :label="trans('crm.contacts.fields.title')"
                    name="title"
                    :error="form.errors.title"
                >
                    <UInput
                        v-model="form.title"
                        :disabled="readonly"
                        :placeholder="
                            readonly
                                ? undefined
                                : trans('crm.contacts.fields.title_placeholder')
                        "
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    class="md:col-span-2"
                    :label="trans('crm.contacts.fields.first_name')"
                    name="first_name"
                    :error="form.errors.first_name"
                    required
                >
                    <UInput
                        v-model="form.first_name"
                        :disabled="readonly"
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    class="md:col-span-3"
                    :label="trans('crm.contacts.fields.last_name')"
                    name="last_name"
                    :error="form.errors.last_name"
                    required
                >
                    <UInput
                        v-model="form.last_name"
                        :disabled="readonly"
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    class="md:col-span-3"
                    :label="trans('crm.contacts.fields.email')"
                    name="email"
                    :error="form.errors.email"
                    required
                >
                    <UInput
                        v-model="form.email"
                        :disabled="readonly"
                        class="w-full"
                        type="email"
                    />
                </UFormField>

                <UFormField
                    class="md:col-span-3"
                    :label="trans('crm.contacts.fields.telephone')"
                    name="telephone"
                    :error="form.errors.telephone"
                >
                    <UInput
                        v-model="form.telephone"
                        :disabled="readonly"
                        class="w-full"
                        type="tel"
                    />
                </UFormField>

                <UFormField
                    class="md:col-span-6"
                    :label="trans('crm.contacts.fields.is_spam')"
                    :help="
                        readonly
                            ? undefined
                            : trans('crm.contacts.fields.is_spam_help')
                    "
                    name="is_spam"
                    :error="form.errors.is_spam"
                >
                    <USwitch v-model="form.is_spam" :disabled="readonly" />
                </UFormField>
            </div>
        </UPageCard>

        <UPageCard
            variant="subtle"
            :title="trans('crm.contacts.marketing.title')"
            :description="trans('crm.contacts.marketing.description')"
        >
            <div class="flex flex-wrap gap-6">
                <UCheckbox
                    v-for="field in marketingFields"
                    :key="field"
                    v-model="form[field]"
                    :disabled="readonly"
                    :label="trans(`crm.contacts.marketing.${field}`)"
                    :name="field"
                />
            </div>
        </UPageCard>

        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="font-semibold">
                    {{ trans('crm.contacts.addresses.title') }}
                </h2>
                <UButton
                    v-if="!readonly"
                    color="neutral"
                    variant="subtle"
                    icon="i-lucide-plus"
                    :label="trans('crm.contacts.addresses.add')"
                    @click="addAddress"
                />
            </div>

            <p v-if="errors.addresses" class="text-error text-sm">
                {{ errors.addresses }}
            </p>

            <p v-if="!form.addresses.length" class="text-muted text-sm">
                {{ trans('crm.contacts.addresses.empty') }}
            </p>

            <AddressFields
                v-for="(_, index) in form.addresses"
                :key="index"
                v-model="form.addresses[index]"
                :path="`addresses.${index}`"
                :title="`${trans('crm.address.title')} ${index + 1}`"
                :errors="errors"
                :readonly="readonly"
            >
                <template v-if="!readonly" #footer>
                    <UButton
                        color="neutral"
                        variant="ghost"
                        icon="i-lucide-trash"
                        :label="trans('crm.contacts.addresses.remove')"
                        @click="removeAddress(index)"
                    />
                </template>
            </AddressFields>
        </div>

        <div v-if="!readonly" class="flex justify-end gap-2">
            <UButton
                :loading="form.processing"
                color="primary"
                :label="
                    create
                        ? trans('crm.contacts.create.submit')
                        : trans('crm.save_changes')
                "
                type="submit"
            />
        </div>
    </UForm>
</template>
