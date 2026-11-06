<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import {
    index,
    store,
    update,
} from '@/actions/App/Http/Controllers/Central/Admin/TenantController';
import type { SelectItem, TenantData, TenantIndustryOption } from '@/types';

const props = defineProps<{
    /** The tenant being edited; left out when creating one */
    tenant?: TenantData;
    modules: SelectItem[];
    industries: TenantIndustryOption[];
}>();

const { trans } = useTrans();

const form = useForm({
    name: props.tenant?.name ?? '',
    industry: props.tenant?.industry.value ?? 'other',
    modules: props.tenant
        ? props.tenant.modules.map((module) => module.value)
        : props.modules.map((module) => String(module.value)),
    domain: '',
    admin_first_name: '',
    admin_last_name: '',
    admin_email: '',
    platform_fee_percent: props.tenant?.platform_fee_percent ?? '',
    platform_fee_fixed: props.tenant?.platform_fee_fixed ?? '',
});

const selectedIndustry = computed(() =>
    props.industries.find((industry) => industry.value === form.industry),
);

const suggestedModuleNames = computed(() =>
    props.modules
        .filter((module) =>
            selectedIndustry.value?.suggested_modules.includes(
                String(module.value),
            ),
        )
        .map((module) => module.label)
        .join(', '),
);

/** A new tenant starts with what suits its industry; an existing one keeps what it has. */
function onIndustryChange() {
    if (!props.tenant && selectedIndustry.value) {
        form.modules = [...selectedIndustry.value.suggested_modules];
    }
}

function toggleModule(value: string, enabled: boolean | 'indeterminate') {
    form.modules =
        enabled === true
            ? [...form.modules, value]
            : form.modules.filter((module) => module !== value);
}

const title = computed(() =>
    props.tenant
        ? trans('tenants.edit.title', { name: props.tenant.name })
        : trans('tenants.create.title'),
);

const breadcrumbs = computed(() => [
    { label: trans('tenants.index.title'), to: index.url() },
    { label: props.tenant?.name ?? trans('tenants.create.title') },
]);

function onSubmit() {
    if (props.tenant) {
        form.transform((data) => ({
            name: data.name,
            industry: data.industry,
            modules: data.modules,
            platform_fee_percent: data.platform_fee_percent,
            platform_fee_fixed: data.platform_fee_fixed,
        })).submit(update({ tenant: props.tenant.id }));

        return;
    }

    form.submit(store());
}
</script>

<template>
    <EditorLayout
        :title="title"
        :breadcrumbs="breadcrumbs"
        :is-dirty="form.isDirty"
        :processing="form.processing"
        :submit-label="tenant ? undefined : trans('tenants.create.submit')"
        narrow
        @submit="onSubmit"
    >
        <UPageCard variant="subtle" :title="trans('tenants.sections.tenant')">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <UFormField
                    :label="trans('tenants.fields.name')"
                    :help="trans('tenants.fields.name_help')"
                    :error="form.errors.name"
                    required
                >
                    <UInput
                        v-model="form.name"
                        placeholder="acme-ltd"
                        class="w-full font-mono"
                    />
                </UFormField>

                <UFormField
                    v-if="!tenant"
                    :label="trans('tenants.fields.domain')"
                    :help="trans('tenants.fields.domain_help')"
                    :error="form.errors.domain"
                    required
                >
                    <UInput
                        v-model="form.domain"
                        placeholder="acme.example.com"
                        class="w-full font-mono"
                    />
                </UFormField>

                <UFormField
                    :label="trans('tenants.fields.industry')"
                    :description="trans('tenants.fields.industry_help')"
                    :help="selectedIndustry?.description"
                    :error="form.errors.industry"
                    class="md:col-span-2"
                    required
                >
                    <USelect
                        v-model="form.industry"
                        :items="industries"
                        class="w-full"
                        @update:model-value="onIndustryChange"
                    />
                </UFormField>
            </div>
        </UPageCard>

        <UPageCard
            variant="subtle"
            :title="trans('tenants.sections.modules')"
            :description="trans('tenants.fields.modules_help')"
        >
            <UFormField :error="form.errors.modules">
                <div class="flex flex-wrap gap-6">
                    <UCheckbox
                        v-for="module in modules"
                        :key="module.value"
                        :label="module.label"
                        :model-value="
                            form.modules.includes(String(module.value))
                        "
                        @update:model-value="
                            (enabled) =>
                                toggleModule(String(module.value), enabled)
                        "
                    />
                </div>
                <p v-if="suggestedModuleNames" class="text-muted mt-3 text-sm">
                    {{
                        trans('tenants.fields.modules_suggested', {
                            modules: suggestedModuleNames,
                        })
                    }}
                </p>
            </UFormField>
        </UPageCard>

        <UPageCard
            v-if="tenant"
            variant="subtle"
            :title="trans('tenants.sections.payments')"
            :description="
                trans(
                    tenant.stripe_connected
                        ? 'tenants.fields.stripe_connected'
                        : 'tenants.fields.stripe_not_connected',
                )
            "
        >
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <UFormField
                    :label="trans('tenants.fields.platform_fee_percent')"
                    :error="form.errors.platform_fee_percent"
                >
                    <UInput
                        v-model="form.platform_fee_percent"
                        type="number"
                        min="0"
                        max="50"
                        step="0.01"
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    :label="trans('tenants.fields.platform_fee_fixed')"
                    :error="form.errors.platform_fee_fixed"
                >
                    <UInput
                        v-model="form.platform_fee_fixed"
                        type="number"
                        min="0"
                        step="0.01"
                        class="w-full"
                    />
                </UFormField>
            </div>
            <p class="text-muted text-sm">
                {{ trans('tenants.fields.platform_fee_help') }}
            </p>
        </UPageCard>

        <UPageCard
            v-if="!tenant"
            variant="subtle"
            :title="trans('tenants.sections.admin')"
            :description="trans('tenants.sections.admin_description')"
        >
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <UFormField
                    :label="trans('tenants.fields.admin_first_name')"
                    :error="form.errors.admin_first_name"
                >
                    <UInput v-model="form.admin_first_name" class="w-full" />
                </UFormField>

                <UFormField
                    :label="trans('tenants.fields.admin_last_name')"
                    :error="form.errors.admin_last_name"
                >
                    <UInput v-model="form.admin_last_name" class="w-full" />
                </UFormField>

                <UFormField
                    :label="trans('tenants.fields.admin_email')"
                    :error="form.errors.admin_email"
                >
                    <UInput
                        v-model="form.admin_email"
                        type="email"
                        class="w-full"
                    />
                </UFormField>
            </div>
        </UPageCard>
    </EditorLayout>
</template>
