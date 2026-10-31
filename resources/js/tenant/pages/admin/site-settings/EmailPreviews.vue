<script setup lang="ts">
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
        <SettingsPage
            :title="trans('site_settings.email_previews.title')"
            :description="trans('site_settings.email_previews.description')"
        >
            <template v-if="selected" #actions>
                <UButton
                    :href="selected.url"
                    target="_blank"
                    color="neutral"
                    variant="outline"
                    trailing-icon="i-lucide-external-link"
                    :label="trans('site_settings.email_previews.open')"
                />
            </template>

            <div class="border-default flex flex-col gap-4 border-t pt-6">
                <USelect
                    v-model="selectedKey"
                    :items="items"
                    :placeholder="trans('site_settings.email_previews.choose')"
                    :aria-label="trans('site_settings.email_previews.choose')"
                    class="w-full sm:w-80"
                />

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
        </SettingsPage>
    </SiteSettingsSubLayout>
</template>
