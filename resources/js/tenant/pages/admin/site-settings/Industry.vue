<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { update } from '@/actions/App/Http/Controllers/Tenant/Admin/SiteSettings/IndustrySettingsController';
import SiteSettingsSubLayout from '@/sublayouts/admin/SiteSettingsSubLayout.vue';
import type { SelectItem } from '@/types';

type Term = { singular: string; plural: string };

type IndustryOption = {
    value: string;
    label: string;
    description: string;
    terms: Record<string, Term>;
    booking_types: string[];
    customer_fields: string[];
    suggested_modules: string[];
};

const props = defineProps<{
    settings: {
        industry: string;
        /** Only the words this tenant has changed. */
        terms: Record<string, Term>;
        booking_types: string[];
        customer_fields: string[];
    };
    industries: IndustryOption[];
    termNames: Array<{ value: string; label: string }>;
    bookingTypes: SelectItem[];
    customerFields: Array<{ value: string; label: string; sensitive: boolean }>;
    modules: SelectItem[];
    activeModules: string[];
    /** False for users who can see the settings but not change them. */
    canManage: boolean;
}>();

const { trans } = useTrans();

/** Every term gets its two inputs; an empty pair means "use the industry's word". */
function termInputs(saved: Record<string, Term>): Record<string, Term> {
    return Object.fromEntries(
        props.termNames.map((term) => [
            term.value,
            {
                singular: saved[term.value]?.singular ?? '',
                plural: saved[term.value]?.plural ?? '',
            },
        ]),
    );
}

const form = useForm({
    industry: props.settings.industry,
    terms: termInputs(props.settings.terms),
    booking_types: [...props.settings.booking_types],
    customer_fields: [...props.settings.customer_fields],
});

const selectedIndustry = computed(
    () =>
        props.industries.find((industry) => industry.value === form.industry) ??
        props.industries[0],
);

function isSameList(first: string[], second: string[]): boolean {
    return (
        first.length === second.length &&
        first.every((value) => second.includes(value))
    );
}

/**
 * Lists still on the old industry's defaults move to the new one's. Lists the tenant has changed
 * stay as they are, matching what's saved.
 */
function changeIndustry(industry: string) {
    const old = selectedIndustry.value;

    form.industry = industry;

    if (isSameList(form.booking_types, old.booking_types)) {
        form.booking_types = [...selectedIndustry.value.booking_types];
    }

    if (isSameList(form.customer_fields, old.customer_fields)) {
        form.customer_fields = [...selectedIndustry.value.customer_fields];
    }
}

const usesDefaultBookingTypes = computed(() =>
    isSameList(form.booking_types, selectedIndustry.value.booking_types),
);

const usesDefaultCustomerFields = computed(() =>
    isSameList(form.customer_fields, selectedIndustry.value.customer_fields),
);

/** The words as they'd be if saved now, for the placeholders and preview below. */
const previewReplacements = computed(() => {
    const replacements: Record<string, string> = {};

    for (const { value: key } of props.termNames) {
        const term = form.terms[key];
        const fallback = selectedIndustry.value.terms[key];

        replacements[key] = term.singular.trim() || fallback.singular;
        replacements[`${key}s`] = term.plural.trim() || fallback.plural;
    }

    return replacements;
});

function toggle(list: string[], value: string, enabled: boolean): string[] {
    return enabled
        ? [...list, value]
        : list.filter((existing) => existing !== value);
}

function discard() {
    form.reset();
    form.clearErrors();
}

function onSubmit() {
    if (!props.canManage) return;

    form.submit(update(), {
        onSuccess: () => {
            form.terms = termInputs(props.settings.terms);
            form.defaults();
        },
    });
}
</script>

<template>
    <SiteSettingsSubLayout>
        <SettingsPage
            :title="trans('site_settings.industry.title')"
            :description="trans('site_settings.industry.description')"
            :readonly="!canManage"
            form-id="industry-settings"
            :is-dirty="form.isDirty"
            :processing="form.processing"
            @discard="discard"
        >
            <form id="industry-settings" @submit.prevent="onSubmit">
                <SettingsRow
                    :label="trans('site_settings.industry.industry')"
                    :description="trans('site_settings.industry.industry_help')"
                    required
                >
                    <UFormField
                        :help="selectedIndustry.description"
                        :error="form.errors.industry"
                    >
                        <USelect
                            :model-value="form.industry"
                            :items="industries"
                            :disabled="!canManage"
                            :aria-label="
                                trans('site_settings.industry.industry')
                            "
                            class="w-full sm:w-80"
                            @update:model-value="changeIndustry"
                        />
                    </UFormField>
                </SettingsRow>

                <SettingsRow
                    :label="trans('site_settings.industry.terms')"
                    :description="trans('site_settings.industry.terms_help')"
                >
                    <div
                        class="text-muted hidden grid-cols-3 gap-3 text-xs font-medium sm:grid"
                        aria-hidden="true"
                    >
                        <span />
                        <span>{{
                            trans('site_settings.industry.singular')
                        }}</span>
                        <span>{{
                            trans('site_settings.industry.plural')
                        }}</span>
                    </div>
                    <div
                        v-for="term in termNames"
                        :key="term.value"
                        class="grid grid-cols-1 gap-3 sm:grid-cols-3 sm:items-start"
                    >
                        <p class="text-default pt-1.5 text-sm font-medium">
                            {{ term.label }}
                        </p>
                        <UFormField
                            :error="form.errors[`terms.${term.value}.singular`]"
                        >
                            <UInput
                                v-model="form.terms[term.value].singular"
                                :placeholder="
                                    selectedIndustry.terms[term.value].singular
                                "
                                :disabled="!canManage"
                                :aria-label="`${term.label}: ${trans('site_settings.industry.singular')}`"
                                class="w-full"
                            />
                        </UFormField>
                        <UFormField
                            :error="form.errors[`terms.${term.value}.plural`]"
                        >
                            <UInput
                                v-model="form.terms[term.value].plural"
                                :placeholder="
                                    selectedIndustry.terms[term.value].plural
                                "
                                :disabled="!canManage"
                                :aria-label="`${term.label}: ${trans('site_settings.industry.plural')}`"
                                class="w-full"
                            />
                        </UFormField>
                    </div>

                    <UAlert
                        color="neutral"
                        variant="subtle"
                        icon="i-lucide-eye"
                        :title="trans('site_settings.industry.preview')"
                        :description="
                            trans(
                                'site_settings.industry.preview_text',
                                previewReplacements,
                            )
                        "
                    />
                </SettingsRow>

                <SettingsRow
                    :label="trans('site_settings.industry.booking_types')"
                    :description="
                        trans(
                            'site_settings.industry.booking_types_help',
                            previewReplacements,
                        )
                    "
                >
                    <UFormField :error="form.errors.booking_types">
                        <div class="flex flex-wrap gap-x-6 gap-y-3">
                            <UCheckbox
                                v-for="type in bookingTypes"
                                :key="type.value"
                                :label="type.label"
                                :disabled="!canManage"
                                :model-value="
                                    form.booking_types.includes(
                                        String(type.value),
                                    )
                                "
                                @update:model-value="
                                    (enabled) =>
                                        (form.booking_types = toggle(
                                            form.booking_types,
                                            String(type.value),
                                            enabled === true,
                                        ))
                                "
                            />
                        </div>
                    </UFormField>
                    <div v-if="canManage && !usesDefaultBookingTypes">
                        <UButton
                            color="neutral"
                            variant="link"
                            class="px-0"
                            :label="trans('site_settings.industry.reset')"
                            @click="
                                form.booking_types = [
                                    ...selectedIndustry.booking_types,
                                ]
                            "
                        />
                    </div>
                </SettingsRow>

                <SettingsRow
                    :label="
                        trans(
                            'site_settings.industry.customer_fields',
                            previewReplacements,
                        )
                    "
                    :description="
                        trans(
                            'site_settings.industry.customer_fields_help',
                            previewReplacements,
                        )
                    "
                >
                    <UFormField :error="form.errors.customer_fields">
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div
                                v-for="field in customerFields"
                                :key="field.value"
                                class="flex items-center gap-2"
                            >
                                <UCheckbox
                                    :label="field.label"
                                    :disabled="!canManage"
                                    :model-value="
                                        form.customer_fields.includes(
                                            field.value,
                                        )
                                    "
                                    @update:model-value="
                                        (enabled) =>
                                            (form.customer_fields = toggle(
                                                form.customer_fields,
                                                field.value,
                                                enabled === true,
                                            ))
                                    "
                                />
                                <UBadge
                                    v-if="field.sensitive"
                                    color="warning"
                                    variant="subtle"
                                    size="sm"
                                >
                                    {{
                                        trans(
                                            'site_settings.industry.sensitive',
                                        )
                                    }}
                                </UBadge>
                            </div>
                        </div>
                    </UFormField>
                    <div v-if="canManage && !usesDefaultCustomerFields">
                        <UButton
                            color="neutral"
                            variant="link"
                            class="px-0"
                            :label="trans('site_settings.industry.reset')"
                            @click="
                                form.customer_fields = [
                                    ...selectedIndustry.customer_fields,
                                ]
                            "
                        />
                    </div>
                </SettingsRow>

                <SettingsRow
                    :label="trans('site_settings.industry.suggested_modules')"
                    :description="
                        trans('site_settings.industry.suggested_modules_help')
                    "
                >
                    <div class="flex flex-wrap gap-2">
                        <UBadge
                            v-for="module in modules.filter((candidate) =>
                                selectedIndustry.suggested_modules.includes(
                                    String(candidate.value),
                                ),
                            )"
                            :key="module.value"
                            :color="
                                activeModules.includes(String(module.value))
                                    ? 'success'
                                    : 'neutral'
                            "
                            variant="subtle"
                        >
                            {{ module.label }}:
                            {{
                                activeModules.includes(String(module.value))
                                    ? trans('site_settings.industry.module_on')
                                    : trans('site_settings.industry.module_off')
                            }}
                        </UBadge>
                    </div>
                </SettingsRow>
            </form>
        </SettingsPage>
    </SiteSettingsSubLayout>
</template>
