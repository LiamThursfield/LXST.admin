<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    destroy,
    disable,
    edit,
    enable,
    index,
} from '@/actions/App/Http/Controllers/Central/Admin/TenantController';
import { formatDateTime, userTimezone } from '@/lib/timezone';
import type { TenantData } from '@/types';
import TenantDomains from './components/TenantDomains.vue';

const props = defineProps<{
    tenant: { data: TenantData };
}>();

const { trans } = useTrans();
const timezone = userTimezone(useAuth().value.user);

const tenant = computed(() => props.tenant.data);
const title = computed(() =>
    trans('tenants.show.title', { name: tenant.value.name }),
);

const disableModalOpen = ref(false);
const deleteModalOpen = ref(false);
const statusForm = useForm({});
const deleteForm = useForm({ confirm_name: '' });

function disableTenant() {
    statusForm.submit(disable({ tenant: tenant.value.id }), {
        preserveScroll: true,
        onSuccess: () => (disableModalOpen.value = false),
    });
}

function enableTenant() {
    statusForm.submit(enable({ tenant: tenant.value.id }), {
        preserveScroll: true,
    });
}

function deleteTenant() {
    deleteForm.submit(destroy({ tenant: tenant.value.id }), {
        preserveScroll: true,
    });
}

watch(deleteModalOpen, (open) => {
    if (!open) {
        deleteForm.reset();
        deleteForm.clearErrors();
    }
});
</script>

<template>
    <UDashboardPanel>
        <template #header>
            <UDashboardNavbar :title="title">
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>

                <template #right>
                    <UButton
                        as="Link"
                        :href="index.url()"
                        color="neutral"
                        variant="ghost"
                        icon="i-lucide-arrow-left"
                        :label="trans('tenants.show.back')"
                    />
                    <UButton
                        as="Link"
                        :href="edit.url(tenant.id)"
                        color="neutral"
                        variant="outline"
                        icon="i-lucide-pencil"
                        :label="trans('common.actions.edit')"
                    />
                    <UButton
                        v-if="tenant.is_disabled"
                        color="neutral"
                        variant="outline"
                        icon="i-lucide-power"
                        :loading="statusForm.processing"
                        :label="trans('tenants.actions.enable')"
                        @click="enableTenant"
                    />
                    <UButton
                        v-else
                        color="warning"
                        variant="outline"
                        icon="i-lucide-power-off"
                        :label="trans('tenants.actions.disable')"
                        @click="disableModalOpen = true"
                    />
                    <UButton
                        v-if="tenant.is_disabled"
                        color="error"
                        variant="outline"
                        icon="i-lucide-trash"
                        :label="trans('tenants.actions.delete')"
                        @click="deleteModalOpen = true"
                    />
                    <UButton
                        v-if="tenant.admin_url && !tenant.is_disabled"
                        :href="tenant.admin_url"
                        target="_blank"
                        color="primary"
                        trailing-icon="i-lucide-external-link"
                        :label="trans('tenants.show.open_admin')"
                    />
                </template>
            </UDashboardNavbar>
        </template>

        <template #body>
            <Head :title="title" />
            <UAlert
                v-if="tenant.is_disabled && tenant.disabled_at"
                class="mb-6"
                color="warning"
                variant="subtle"
                icon="i-lucide-power-off"
                :title="trans('tenants.status.disabled')"
                :description="
                    trans('tenants.disable.disabled_since', {
                        date: formatDateTime(tenant.disabled_at, timezone),
                    })
                "
            />
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <UCard>
                    <template #header>
                        <h2 class="font-semibold">
                            {{ trans('tenants.show.details') }}
                        </h2>
                    </template>

                    <dl
                        class="grid grid-cols-[auto_1fr] gap-x-6 gap-y-3 text-sm"
                    >
                        <dt class="text-muted">
                            {{ trans('tenants.columns.name') }}
                        </dt>
                        <dd>{{ tenant.name }}</dd>
                        <dt class="text-muted">
                            {{ trans('tenants.columns.id') }}
                        </dt>
                        <dd class="font-mono text-xs leading-5">
                            {{ tenant.id }}
                        </dd>
                        <dt class="text-muted">
                            {{ trans('tenants.columns.created_at') }}
                        </dt>
                        <dd>
                            {{
                                tenant.created_at
                                    ? formatDateTime(
                                          tenant.created_at,
                                          timezone,
                                      )
                                    : ''
                            }}
                        </dd>
                    </dl>
                </UCard>

                <UCard>
                    <template #header>
                        <h2 class="font-semibold">
                            {{ trans('tenants.show.modules') }}
                        </h2>
                    </template>

                    <div
                        v-if="tenant.modules.length"
                        class="flex flex-wrap gap-2"
                    >
                        <UBadge
                            v-for="module in tenant.modules"
                            :key="module.value"
                            color="neutral"
                            variant="subtle"
                        >
                            {{ module.label }}
                        </UBadge>
                    </div>
                    <p v-else class="text-muted text-sm">
                        {{ trans('tenants.show.no_modules') }}
                    </p>
                </UCard>

                <TenantDomains class="lg:col-span-2" :tenant="tenant" />
            </div>

            <UModal
                v-model:open="disableModalOpen"
                :title="
                    trans('common.confirmation.title', {
                        action: trans('tenants.actions.disable'),
                    })
                "
                :description="
                    trans('tenants.disable.confirm', { name: tenant.name })
                "
            >
                <template #body>
                    <div class="flex justify-end gap-2">
                        <UButton
                            color="neutral"
                            variant="subtle"
                            :label="trans('common.actions.cancel')"
                            @click="disableModalOpen = false"
                        />
                        <UButton
                            color="warning"
                            :loading="statusForm.processing"
                            :label="trans('tenants.actions.disable')"
                            @click="disableTenant"
                        />
                    </div>
                </template>
            </UModal>

            <UModal
                v-model:open="deleteModalOpen"
                :title="trans('tenants.delete.title', { name: tenant.name })"
                :description="trans('tenants.delete.warning')"
            >
                <template #body>
                    <form class="space-y-4" @submit.prevent="deleteTenant">
                        <UFormField
                            :label="
                                trans('tenants.delete.confirm_label', {
                                    name: tenant.name,
                                })
                            "
                            :error="
                                deleteForm.errors.confirm_name ||
                                (deleteForm.errors as Record<string, string>)
                                    .tenant
                            "
                        >
                            <UInput
                                v-model="deleteForm.confirm_name"
                                class="w-full font-mono"
                                autocomplete="off"
                            />
                        </UFormField>
                        <div class="flex justify-end gap-2">
                            <UButton
                                color="neutral"
                                variant="subtle"
                                :label="trans('common.actions.cancel')"
                                @click="deleteModalOpen = false"
                            />
                            <UButton
                                type="submit"
                                color="error"
                                :disabled="
                                    deleteForm.confirm_name !== tenant.name
                                "
                                :loading="deleteForm.processing"
                                :label="trans('tenants.delete.submit')"
                            />
                        </div>
                    </form>
                </template>
            </UModal>
        </template>
    </UDashboardPanel>
</template>
