<script setup lang="ts">
import type { AddressData } from '@/types';

const props = withDefaults(
    defineProps<{
        /** Server validation errors, keyed by the full field name (e.g. `address.postcode`). */
        errors?: Record<string, string | undefined>;
        readonly?: boolean;
        /** The field path the address is submitted under, e.g. `address` or `addresses.0`. */
        path?: string;
        title?: string;
        /** Shown when not readonly; defaults to the "leave blank for no address" hint. */
        description?: string;
    }>(),
    {
        errors: () => ({}),
        readonly: false,
        path: 'address',
        title: undefined,
        description: undefined,
    },
);

const address = defineModel<AddressData>({ required: true });

const { trans } = useTrans();

/** The full field name for an address field, e.g. `addresses.0.postcode`. */
function fieldName(field: string): string {
    return `${props.path}.${field}`;
}

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
        :title="title ?? trans('crm.address.title')"
        :description="
            readonly
                ? undefined
                : (description ?? trans('crm.address.description'))
        "
    >
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <UFormField
                class="md:col-span-2"
                :label="trans('crm.address.line_1')"
                :name="fieldName('line_1')"
                :error="errors[fieldName('line_1')]"
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
                :name="fieldName(field)"
                :error="errors[fieldName(field)]"
            >
                <UInput
                    v-model="address[field]"
                    :disabled="readonly"
                    class="w-full"
                />
            </UFormField>

            <UFormField
                :label="trans('crm.address.postcode')"
                :name="fieldName('postcode')"
                :error="errors[fieldName('postcode')]"
            >
                <UInput
                    v-model="address.postcode"
                    :disabled="readonly"
                    class="w-full"
                    maxlength="10"
                />
            </UFormField>
        </div>

        <template v-if="$slots.footer" #footer>
            <slot name="footer" />
        </template>
    </UPageCard>
</template>
