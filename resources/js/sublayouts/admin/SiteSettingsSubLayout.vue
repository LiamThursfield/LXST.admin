<script setup lang="ts">
import type { NavigationMenuItem } from '@nuxt/ui';
import { index as emailPreviews } from '@/actions/App/Http/Controllers/Tenant/Admin/SiteSettings/EmailPreviewController';
import { edit as editGeneral } from '@/actions/App/Http/Controllers/Tenant/Admin/SiteSettings/GeneralSettingsController';
import { edit as editIntegrations } from '@/actions/App/Http/Controllers/Tenant/Admin/SiteSettings/IntegrationSettingsController';
import { edit as editMail } from '@/actions/App/Http/Controllers/Tenant/Admin/SiteSettings/MailSettingsController';
import { edit as editTheme } from '@/actions/App/Http/Controllers/Tenant/Admin/SiteSettings/ThemeSettingsController';

const { trans } = useTrans();

const links = [
    {
        label: trans('site_settings.general.nav'),
        icon: 'i-lucide-settings',
        to: editGeneral.url(),
    },
    {
        label: trans('site_settings.theme.nav'),
        icon: 'i-lucide-palette',
        to: editTheme.url(),
    },
    {
        label: trans('site_settings.mail.nav'),
        icon: 'i-lucide-mail',
        to: editMail.url(),
    },
    {
        label: trans('site_settings.integrations.nav'),
        icon: 'i-lucide-plug',
        to: editIntegrations.url(),
    },
    {
        label: trans('site_settings.email_previews.nav'),
        icon: 'i-lucide-mail-search',
        to: emailPreviews.url(),
    },
] satisfies NavigationMenuItem[];
</script>

<template>
    <UDashboardPanel>
        <template #header>
            <UDashboardNavbar :title="trans('site_settings.title')">
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>
            </UDashboardNavbar>

            <UDashboardToolbar>
                <!-- NOTE: The `-mx-1` class is used to align with the `DashboardSidebarCollapse` button here. -->
                <UNavigationMenu
                    :items="links"
                    highlight
                    class="-mx-1 flex-1"
                />
            </UDashboardToolbar>
        </template>

        <template #body>
            <div
                class="mx-auto flex w-full flex-col gap-4 sm:gap-6 lg:max-w-2xl lg:gap-12"
            >
                <slot />
            </div>
        </template>
    </UDashboardPanel>
</template>
