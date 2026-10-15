<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import * as z from 'zod';
import {
    store,
    update,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Crm/OrganisationUnitController';
import AddressFields from '@/tenant/components/crm/AddressFields.vue';
import type {
    AddressData,
    OrganisationUnitData,
    OrganisationUnitFormOptions,
    OrganisationUnitType,
} from '@/types';

const props = withDefaults(
    defineProps<
        OrganisationUnitFormOptions & {
            organisationUnit?: OrganisationUnitData;
            readonly?: boolean;
            create?: boolean;
        }
    >(),
    {
        readonly: false,
        create: false,
    },
);

const { trans } = useTrans();

const schema = z.object({
    type: z.string().min(1),
    name: z
        .string()
        .min(1, trans('crm.organisation_units.validation.name_required'))
        .max(255),
    email: z
        .email(trans('crm.organisation_units.validation.email_invalid'))
        .max(255),
});

const emptyAddress: AddressData = {
    postcode: '',
    line_1: '',
    line_2: null,
    line_3: null,
    town_city: null,
    county: null,
    country: null,
};

const unit = props.organisationUnit;

const form = useForm({
    type: (unit?.type ?? 'company') as OrganisationUnitType,
    parent_id: unit?.parent_id ?? null,
    name: unit?.name ?? '',
    slug: unit?.slug ?? '',
    email: unit?.email ?? '',
    telephone: unit?.telephone ?? '',
    mobile_phone: unit?.mobile_phone ?? '',
    socials: Object.fromEntries(
        props.socialMedia.map((social) => [
            social.value,
            unit?.socials?.[social.value] ?? '',
        ]),
    ) as Record<string, string>,
    notification_emails: [...(unit?.notification_emails ?? [])],
    address: { ...emptyAddress, ...(unit?.address ?? {}) } as AddressData,
});

/** The parent types allowed for the chosen type: none for a company, which is always at the top. */
const allowedParentTypes = computed(
    () => props.allowedParentTypes[form.type] ?? [],
);

/** Only units of an allowed type can be the parent, e.g. a location must be part of a company. */
const parentItems = computed(() =>
    props.parentOptions.filter((option) =>
        allowedParentTypes.value.includes(option.type),
    ),
);

// Clear the parent when it is no longer allowed for the chosen type
watch(
    () => form.type,
    () => {
        const stillAllowed = parentItems.value.some(
            (item) => item.value === form.parent_id,
        );

        if (!stillAllowed) {
            form.parent_id = null;
        }
    },
);

/** Server validation errors, including nested ones such as `address.postcode`. */
const errors = computed(
    () => form.errors as Record<string, string | undefined>,
);

function addNotificationEmail() {
    form.notification_emails.push('');
}

function removeNotificationEmail(index: number) {
    form.notification_emails.splice(index, 1);
}

function onSubmit() {
    if (props.readonly) return;

    if (props.create) {
        form.submit(store());
        return;
    }

    form.submit(update({ organisation_unit: unit!.id }));
}
</script>

<template>
    <UForm :schema="schema" :state="form" class="space-y-6" @submit="onSubmit">
        <UPageCard
            variant="subtle"
            :title="trans('crm.organisation_units.sections.details')"
        >
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <UFormField
                    :label="trans('crm.organisation_units.fields.type')"
                    name="type"
                    :error="form.errors.type"
                    required
                >
                    <USelect
                        v-model="form.type"
                        :disabled="readonly"
                        :items="types"
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    v-if="allowedParentTypes.length"
                    :label="trans('crm.organisation_units.fields.parent')"
                    :description="
                        readonly
                            ? undefined
                            : trans(
                                  'crm.organisation_units.fields.parent_description',
                              )
                    "
                    name="parent_id"
                    :error="form.errors.parent_id"
                    required
                >
                    <USelect
                        v-model="form.parent_id"
                        :disabled="readonly"
                        :items="parentItems"
                        :placeholder="
                            trans(
                                'crm.organisation_units.fields.parent_placeholder',
                            )
                        "
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    :label="trans('crm.organisation_units.fields.name')"
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
                    :label="trans('crm.organisation_units.fields.slug')"
                    :description="
                        readonly
                            ? undefined
                            : trans(
                                  'crm.organisation_units.fields.slug_description',
                              )
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
            </div>
        </UPageCard>

        <UPageCard
            variant="subtle"
            :title="trans('crm.organisation_units.sections.contact')"
        >
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <UFormField
                    class="md:col-span-2"
                    :label="trans('crm.organisation_units.fields.email')"
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
                    :label="trans('crm.organisation_units.fields.telephone')"
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
                    :label="trans('crm.organisation_units.fields.mobile_phone')"
                    name="mobile_phone"
                    :error="form.errors.mobile_phone"
                >
                    <UInput
                        v-model="form.mobile_phone"
                        :disabled="readonly"
                        class="w-full"
                        type="tel"
                    />
                </UFormField>

                <UFormField
                    class="md:col-span-2"
                    :label="
                        trans(
                            'crm.organisation_units.fields.notification_emails',
                        )
                    "
                    :description="
                        readonly
                            ? undefined
                            : trans(
                                  'crm.organisation_units.fields.notification_emails_description',
                              )
                    "
                    name="notification_emails"
                    :error="form.errors.notification_emails"
                >
                    <div class="flex flex-col gap-2">
                        <div
                            v-for="(_, index) in form.notification_emails"
                            :key="index"
                            class="flex flex-col gap-1"
                        >
                            <div class="flex gap-2">
                                <UInput
                                    v-model="form.notification_emails[index]"
                                    :disabled="readonly"
                                    :placeholder="
                                        trans(
                                            'crm.organisation_units.fields.notification_email_placeholder',
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
                                            'crm.organisation_units.fields.remove_notification_email',
                                        )
                                    "
                                    @click="removeNotificationEmail(index)"
                                />
                            </div>
                            <p
                                v-if="errors[`notification_emails.${index}`]"
                                class="text-error text-sm"
                            >
                                {{ errors[`notification_emails.${index}`] }}
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
                                    'crm.organisation_units.fields.add_notification_email',
                                )
                            "
                            @click="addNotificationEmail"
                        />
                    </div>
                </UFormField>
            </div>
        </UPageCard>

        <UPageCard
            variant="subtle"
            :title="trans('crm.organisation_units.sections.socials')"
        >
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <UFormField
                    v-for="social in socialMedia"
                    :key="social.value"
                    :label="social.label"
                    :name="`socials.${social.value}`"
                    :error="errors[`socials.${social.value}`]"
                >
                    <UInput
                        v-model="form.socials[social.value]"
                        :disabled="readonly"
                        class="w-full"
                        type="url"
                    />
                </UFormField>
            </div>
        </UPageCard>

        <AddressFields
            v-model="form.address"
            :errors="errors"
            :readonly="readonly"
        />

        <div v-if="!readonly" class="flex justify-end gap-2">
            <UButton
                :loading="form.processing"
                color="primary"
                :label="
                    create
                        ? trans('crm.organisation_units.create.submit')
                        : trans('crm.save_changes')
                "
                type="submit"
            />
        </div>
    </UForm>
</template>
