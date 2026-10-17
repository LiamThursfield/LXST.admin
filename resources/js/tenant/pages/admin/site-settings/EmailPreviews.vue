<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import SiteSettingsSubLayout from '@/sublayouts/admin/SiteSettingsSubLayout.vue';

type EmailPreview = { key: string; group: string; name: string; url: string };

const props = defineProps<{
    groups: Array<{ label: string; emails: EmailPreview[] }>;
}>();

const { trans } = useTrans();

/** One group of options per kind of email, headed by its label */
const items = computed(() =>
    props.groups.map((group) => [
        { type: 'label' as const, label: group.label },
        ...group.emails.map((email) => ({
            label: email.name,
            value: email.key,
        })),
    ]),
);

const emails = computed(() => props.groups.flatMap((group) => group.emails));
const selectedKey = ref<string | undefined>(emails.value[0]?.key);
const selected = computed(() =>
    emails.value.find((email) => email.key === selectedKey.value),
);
</script>

<template>
    <SiteSettingsSubLayout>
        <Head :title="trans('site_settings.email_previews.title')" />

        <UPageCard
            :title="trans('site_settings.email_previews.title')"
            :description="trans('site_settings.email_previews.description')"
            variant="naked"
        />

        <div class="flex flex-col gap-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <USelect
                    v-model="selectedKey"
                    :items="items"
                    :placeholder="trans('site_settings.email_previews.choose')"
                    class="w-full sm:w-80"
                />
                <UButton
                    v-if="selected"
                    :href="selected.url"
                    target="_blank"
                    color="neutral"
                    variant="ghost"
                    trailing-icon="i-lucide-external-link"
                    :label="trans('site_settings.email_previews.open')"
                />
            </div>

            <UCard v-if="selected" :ui="{ body: 'p-0 sm:p-0' }">
                <iframe
                    :key="selected.key"
                    :src="selected.url"
                    :title="
                        trans('site_settings.email_previews.frame_title', {
                            email: selected.name,
                        })
                    "
                    class="h-[70vh] w-full rounded-lg bg-white"
                    sandbox="allow-same-origin"
                />
            </UCard>
            <p v-else class="text-muted text-sm">
                {{ trans('site_settings.email_previews.choose') }}
            </p>
        </div>
    </SiteSettingsSubLayout>
</template>
