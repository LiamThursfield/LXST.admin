<script setup lang="ts">
import type { AddressData } from '@/types';

withDefaults(
    defineProps<{
        /** Server validation errors, keyed by the full field name (e.g. `address.postcode`). */
        errors?: Record<string, string | undefined>;
        readonly?: boolean;
    }>(),
    {
        errors: () => ({}),
        readonly: false,
    },
);

const address = defineModel<AddressData>({ required: true });

const { trans } = useTrans();

const optionalFields = [
    'line_2',
    'line_3',
    'town_city',
    'county',
    'country',
] as const;
</script>

<template>
    <UPageCard
        variant="subtle"
        :title="trans('crm.address.title')"
        :description="readonly ? undefined : trans('crm.address.description')"
    >
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <UFormField
                class="md:col-span-2"
                :label="trans('crm.address.line_1')"
                name="address.line_1"
                :error="errors['address.line_1']"
            >
                <UInput
                    v-model="address.line_1"
                    :disabled="readonly"
                    class="w-full"
                />
            </UFormField>

            <UFormField
                v-for="field in optionalFields"
                :key="field"
                :label="trans(`crm.address.${field}`)"
                :name="`address.${field}`"
                :error="errors[`address.${field}`]"
            >
                <UInput
                    v-model="address[field]"
                    :disabled="readonly"
                    class="w-full"
                />
            </UFormField>

            <UFormField
                :label="trans('crm.address.postcode')"
                name="address.postcode"
                :error="errors['address.postcode']"
            >
                <UInput
                    v-model="address.postcode"
                    :disabled="readonly"
                    class="w-full"
                    maxlength="10"
                />
            </UFormField>
        </div>
    </UPageCard>
</template>
