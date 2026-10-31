<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import type { NavigationMenuItem } from '@nuxt/ui';
import { edit as editDj } from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/DjSettingsController';
import { index as apiTokens } from '@/actions/App/Http/Controllers/Tenant/Admin/SiteSettings/ApiTokenController';
import { index as emailPreviews } from '@/actions/App/Http/Controllers/Tenant/Admin/SiteSettings/EmailPreviewController';
import { edit as editGeneral } from '@/actions/App/Http/Controllers/Tenant/Admin/SiteSettings/GeneralSettingsController';
import { edit as editIntegrations } from '@/actions/App/Http/Controllers/Tenant/Admin/SiteSettings/IntegrationSettingsController';
import { edit as editMail } from '@/actions/App/Http/Controllers/Tenant/Admin/SiteSettings/MailSettingsController';
import { edit as editTheme } from '@/actions/App/Http/Controllers/Tenant/Admin/SiteSettings/ThemeSettingsController';

const { trans } = useTrans();

const page = usePage();

/**
 * The site settings pages the user can open, by their main menu keys: the menu already leaves
 * out pages the user isn't allowed to see, and the DJ tab when the tenant hasn't the DJ module.
 */
const allowedKeys = computed(
    () =>
        new Set(
            page.props.menus.main
                .flat()
                .flatMap((item: NavigationMenuItem) => item.children ?? [])
                .map(
                    (child: NavigationMenuItem & { key?: string }) => child.key,
                ),
        ),
);

const links = computed(() =>
    (
        [
            {
                key: 'general',
                label: trans('site_settings.general.nav'),
                icon: 'i-lucide-settings',
                to: editGeneral.url(),
            },
            {
                key: 'theme',
                label: trans('site_settings.theme.nav'),
                icon: 'i-lucide-palette',
                to: editTheme.url(),
            },
            {
                key: 'mail',
                label: trans('site_settings.mail.nav'),
                icon: 'i-lucide-mail',
                to: editMail.url(),
            },
            {
                key: 'integrations',
                label: trans('site_settings.integrations.nav'),
                icon: 'i-lucide-plug',
                to: editIntegrations.url(),
            },
            {
                key: 'email-previews',
                label: trans('site_settings.email_previews.nav'),
                icon: 'i-lucide-mail-search',
                to: emailPreviews.url(),
            },
            {
                key: 'site-settings-dj',
                label: trans('site_settings.dj.nav'),
                icon: 'i-lucide-disc-3',
                to: editDj.url(),
            },
            {
                key: 'api-tokens',
                label: trans('site_settings.api_tokens.nav'),
                icon: 'i-lucide-key-round',
                to: apiTokens.url(),
            },
        ] satisfies Array<NavigationMenuItem & { key: string }>
    ).filter((link) => allowedKeys.value.has(link.key)),
);
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
            <div class="mx-auto flex w-full max-w-4xl flex-col gap-6">
                <slot />
            </div>
        </template>
    </UDashboardPanel>
</template>
