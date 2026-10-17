<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { index } from '@/actions/App/Http/Controllers/Central/Admin/TenantController';
import { formatDateTime, userTimezone } from '@/lib/timezone';
import type { TenantData } from '@/types';

const props = defineProps<{
    tenant: { data: TenantData };
}>();

const { trans } = useTrans();
const timezone = userTimezone(useAuth().value.user);

const tenant = computed(() => props.tenant.data);
const title = computed(() =>
    trans('tenants.show.title', { name: tenant.value.name }),
);
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
                        :as="Link"
                        :href="index.url()"
                        color="neutral"
                        variant="ghost"
                        icon="i-lucide-arrow-left"
                        :label="trans('tenants.show.back')"
                    />
                    <UButton
                        v-if="tenant.admin_url"
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

                <UCard class="lg:col-span-2">
                    <template #header>
                        <h2 class="font-semibold">
                            {{ trans('tenants.show.domains') }}
                        </h2>
                    </template>

                    <ul
                        v-if="tenant.domains.length"
                        class="flex flex-col gap-2"
                    >
                        <li
                            v-for="domain in tenant.domains"
                            :key="domain.domain"
                            class="flex items-center justify-between gap-4"
                        >
                            <span class="font-mono text-sm">{{
                                domain.domain
                            }}</span>
                            <UButton
                                v-if="domain.admin_url"
                                :href="domain.admin_url"
                                target="_blank"
                                color="neutral"
                                variant="ghost"
                                size="sm"
                                trailing-icon="i-lucide-external-link"
                                :label="trans('tenants.show.open_admin')"
                            />
                        </li>
                    </ul>
                    <p v-else class="text-muted text-sm">
                        {{ trans('tenants.show.no_domains') }}
                    </p>
                </UCard>
            </div>
        </template>
    </UDashboardPanel>
</template>
