<script setup lang="ts">
import { useForm, usePoll } from '@inertiajs/vue3';
import type { DropdownMenuItem } from '@nuxt/ui';
import {
    destroy,
    disable,
    edit,
    enable,
    index,
    retrySetup,
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
const isReady = computed(() => tenant.value.setup_status.value === 'ready');
const isSettingUp = computed(
    () => tenant.value.setup_status.value === 'setting_up',
);
const setupFailed = computed(
    () => tenant.value.setup_status.value === 'failed',
);

// Check on the setup until it's finished, then say when it's ready
const toast = useToast();
const { start, stop } = usePoll(
    2000,
    { only: ['tenant'] },
    { autoStart: false },
);

watch(
    () => tenant.value.setup_status.value,
    (status, previous) => {
        if (status === 'setting_up') {
            start();
        } else {
            stop();
        }

        if (previous === 'setting_up' && status === 'ready') {
            toast.add({
                title: trans('tenants.setup.ready', {
                    name: tenant.value.name,
                }),
                icon: 'i-lucide-check',
                color: 'success',
            });
        }
    },
    { immediate: true },
);
const title = computed(() =>
    trans('tenants.show.title', { name: tenant.value.name }),
);

const disableModalOpen = ref(false);
const deleteModalOpen = ref(false);
const statusForm = useForm({});
const retryForm = useForm({});
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

function retryTenantSetup() {
    retryForm.submit(retrySetup({ tenant: tenant.value.id }), {
        preserveScroll: true,
    });
}

function deleteTenant() {
    deleteForm.submit(destroy({ tenant: tenant.value.id }), {
        preserveScroll: true,
    });
}

const tab = ref('domains');

const tabs = computed(() => [
    {
        value: 'domains',
        label: trans('tenants.show.domains'),
        count: tenant.value.domains.length,
    },
    {
        value: 'modules',
        label: trans('tenants.show.modules'),
        count: tenant.value.modules.length,
    },
]);

const breadcrumbs = computed(() => [
    { label: trans('tenants.show.back'), to: index.url() },
    { label: tenant.value.name },
]);

const details = computed(() => [
    { label: trans('tenants.columns.name'), value: tenant.value.name },
    { label: trans('tenants.columns.id'), value: tenant.value.id, mono: true },
    {
        label: trans('tenants.columns.industry'),
        value: tenant.value.industry.label,
    },
    {
        label: trans('tenants.columns.status'),
        value: !isReady.value
            ? tenant.value.setup_status.label
            : tenant.value.is_disabled
              ? trans('tenants.status.disabled')
              : trans('tenants.status.active'),
        color: setupFailed.value
            ? 'error'
            : isSettingUp.value
              ? 'info'
              : tenant.value.is_disabled
                ? 'warning'
                : 'success',
    } as const,
    {
        label: trans('tenants.columns.created_at'),
        value: tenant.value.created_at
            ? formatDateTime(tenant.value.created_at, timezone)
            : null,
    },
]);

const deleteItem = computed<DropdownMenuItem>(() => ({
    label: trans('tenants.actions.delete'),
    icon: 'i-lucide-trash',
    color: 'error' as const,
    onSelect: () => (deleteModalOpen.value = true),
}));

const menu = computed<DropdownMenuItem[][]>(() => {
    // Until it's set up there's nothing to switch off; a failed setup can be deleted straight away
    if (!isReady.value) {
        return setupFailed.value ? [[deleteItem.value]] : [];
    }

    return [
        [
            tenant.value.is_disabled
                ? {
                      label: trans('tenants.actions.enable'),
                      icon: 'i-lucide-power',
                      disabled: statusForm.processing,
                      onSelect: enableTenant,
                  }
                : {
                      label: trans('tenants.actions.disable'),
                      icon: 'i-lucide-power-off',
                      color: 'warning',
                      onSelect: () => (disableModalOpen.value = true),
                  },
        ],
        ...(tenant.value.is_disabled ? [[deleteItem.value]] : []),
    ];
});

watch(deleteModalOpen, (open) => {
    if (!open) {
        deleteForm.reset();
        deleteForm.clearErrors();
    }
});
</script>

<template>
    <RecordLayout
        v-model:tab="tab"
        :title="title"
        :breadcrumbs="breadcrumbs"
        :tabs="tabs"
        :menu="menu"
        :edit-href="edit.url(tenant.id)"
    >
        <template v-if="tenant.is_disabled || !isReady" #badges>
            <UBadge
                v-if="!isReady"
                :color="setupFailed ? 'error' : 'info'"
                variant="subtle"
            >
                {{ tenant.setup_status.label }}
            </UBadge>
            <UBadge v-else color="warning" variant="subtle">
                {{ trans('tenants.status.disabled') }}
            </UBadge>
        </template>

        <template
            v-if="tenant.admin_url && isReady && !tenant.is_disabled"
            #actions
        >
            <UButton
                :href="tenant.admin_url"
                target="_blank"
                color="neutral"
                variant="outline"
                trailing-icon="i-lucide-external-link"
                :label="trans('tenants.show.open_admin')"
            />
        </template>

        <template v-if="isSettingUp" #notice>
            <UAlert
                color="info"
                variant="subtle"
                icon="i-lucide-loader-circle"
                :ui="{ icon: 'animate-spin' }"
                :title="
                    trans('tenants.setup.setting_up_title', {
                        name: tenant.name,
                    })
                "
            >
                <template #description>
                    <p>{{ trans('tenants.setup.setting_up_description') }}</p>
                    <p v-if="tenant.setup_admin_email">
                        {{
                            trans('tenants.setup.setting_up_with_admin', {
                                email: tenant.setup_admin_email,
                            })
                        }}
                    </p>
                    <p v-if="tenant.setup_stalled" class="text-warning mt-1">
                        {{ trans('tenants.setup.stalled') }}
                    </p>
                </template>
            </UAlert>
        </template>

        <template v-else-if="setupFailed" #notice>
            <UAlert
                color="error"
                variant="subtle"
                icon="i-lucide-circle-alert"
                :title="
                    trans('tenants.setup.failed_title', { name: tenant.name })
                "
                :actions="[
                    {
                        label: trans('tenants.setup.retry'),
                        icon: 'i-lucide-rotate-ccw',
                        color: 'error',
                        loading: retryForm.processing,
                        onClick: retryTenantSetup,
                    },
                    {
                        label: trans('tenants.actions.delete'),
                        icon: 'i-lucide-trash',
                        color: 'error',
                        variant: 'outline',
                        onClick: () => (deleteModalOpen = true),
                    },
                ]"
            >
                <template #description>
                    <p v-if="tenant.setup_error" class="font-mono text-xs">
                        {{ tenant.setup_error }}
                    </p>
                    <p class="mt-1">
                        {{ trans('tenants.setup.failed_description') }}
                    </p>
                </template>
            </UAlert>
        </template>

        <template v-else-if="tenant.is_disabled && tenant.disabled_at" #notice>
            <UAlert
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
        </template>

        <TenantDomains v-if="tab === 'domains'" :tenant="tenant" />

        <UCard v-else variant="subtle">
            <div v-if="tenant.modules.length" class="flex flex-wrap gap-2">
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

        <template #sidebar>
            <RecordFacts
                :title="trans('tenants.show.details')"
                :facts="details"
            />
        </template>

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
                            (deleteForm.errors as Record<string, string>).tenant
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
                            :disabled="deleteForm.confirm_name !== tenant.name"
                            :loading="deleteForm.processing"
                            :label="trans('tenants.delete.submit')"
                        />
                    </div>
                </form>
            </template>
        </UModal>
    </RecordLayout>
</template>
