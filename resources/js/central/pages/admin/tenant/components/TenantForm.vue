<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import {
    store,
    update,
} from '@/actions/App/Http/Controllers/Central/Admin/TenantController';
import type { SelectItem, TenantData } from '@/types';

const props = defineProps<{
    /** The tenant being edited; left out when creating one */
    tenant?: TenantData;
    modules: SelectItem[];
}>();

const { trans } = useTrans();

const form = useForm({
    name: props.tenant?.name ?? '',
    modules: props.tenant
        ? props.tenant.modules.map((module) => module.value)
        : props.modules.map((module) => String(module.value)),
    domain: '',
    admin_first_name: '',
    admin_last_name: '',
    admin_email: '',
});

function toggleModule(value: string, enabled: boolean | 'indeterminate') {
    form.modules =
        enabled === true
            ? [...form.modules, value]
            : form.modules.filter((module) => module !== value);
}

function onSubmit() {
    if (props.tenant) {
        form.transform((data) => ({
            name: data.name,
            modules: data.modules,
        })).submit(update({ tenant: props.tenant.id }));

        return;
    }

    form.submit(store());
}
</script>

<template>
    <form class="space-y-6" @submit.prevent="onSubmit">
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
            </UFormField>
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

        <div class="flex items-center justify-end gap-4">
            <p v-if="!tenant && form.processing" class="text-muted text-sm">
                {{ trans('tenants.create.provisioning') }}
            </p>
            <UButton
                type="submit"
                color="primary"
                :loading="form.processing"
                :label="
                    tenant
                        ? trans('tenants.edit.submit')
                        : trans('tenants.create.submit')
                "
            />
        </div>
    </form>
</template>
