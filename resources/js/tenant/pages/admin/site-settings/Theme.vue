<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import colors from 'tailwindcss/colors';
import { update } from '@/actions/App/Http/Controllers/Tenant/Admin/SiteSettings/ThemeSettingsController';
import SiteSettingsSubLayout from '@/sublayouts/admin/SiteSettingsSubLayout.vue';
import FileField from '@/tenant/components/file-manager/FileField.vue';

type ThemeSettings = {
    logo_file_id: number | null;
    favicon_file_id: number | null;
    primary_color: string;
    neutral_color: string;
    site_colors: Record<string, string | null>;
};

const props = defineProps<{
    settings: ThemeSettings;
    primaryPalettes: string[];
    neutralPalettes: string[];
    /** The website colours' keys, with the defaults "Reset to defaults" restores. */
    defaultSiteColors: Record<string, string>;
    /** False for users who can see the settings but not change them. */
    canManage: boolean;
}>();

const { trans } = useTrans();

const form = useForm({
    logo_file_id: props.settings.logo_file_id,
    favicon_file_id: props.settings.favicon_file_id,
    primary_color: props.settings.primary_color,
    neutral_color: props.settings.neutral_color,
    site_colors: Object.fromEntries(
        Object.keys(props.defaultSiteColors).map((key) => [
            key,
            props.settings.site_colors[key] ?? '',
        ]),
    ) as Record<string, string>,
});

/** Server validation errors, including nested ones such as `site_colors.primary`. */
const errors = computed(
    () => form.errors as Record<string, string | undefined>,
);

/** The 500 shade of a Tailwind palette, for showing a swatch next to its name. */
function swatch(palette: string): string {
    const shades = (
        colors as unknown as Record<string, Record<number, string>>
    )[palette];

    return shades?.[500] ?? 'transparent';
}

function paletteItems(palettes: string[]) {
    return palettes.map((palette) => ({
        label: trans(`site_settings.theme.palettes.${palette}`),
        value: palette,
    }));
}

const primaryItems = computed(() => paletteItems(props.primaryPalettes));
const neutralItems = computed(() => paletteItems(props.neutralPalettes));

/** A hex colour the browser can show, or nothing while it is being typed. */
function validHex(value: string | null | undefined): string | undefined {
    return value && /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(value)
        ? value
        : undefined;
}

function resetSiteColors() {
    form.site_colors = { ...props.defaultSiteColors };
}

function onSubmit() {
    if (!props.canManage) return;

    // Show what was saved, which the server may have tidied (e.g. upper-cased)
    form.submit(update(), {
        onSuccess: () => {
            form.site_colors = Object.fromEntries(
                Object.keys(props.defaultSiteColors).map((key) => [
                    key,
                    props.settings.site_colors[key] ?? '',
                ]),
            );
            form.defaults();
        },
    });
}
</script>

<template>
    <SiteSettingsSubLayout>
        <Head :title="trans('site_settings.theme.title')" />

        <form
            id="theme-settings"
            class="flex flex-col gap-4"
            @submit.prevent="onSubmit"
        >
            <UPageCard
                :title="trans('site_settings.theme.title')"
                :description="
                    canManage
                        ? trans('site_settings.theme.description')
                        : trans('site_settings.read_only')
                "
                orientation="horizontal"
                variant="naked"
            >
                <UButton
                    v-if="canManage"
                    class="w-fit lg:ms-auto"
                    color="primary"
                    form="theme-settings"
                    type="submit"
                    :loading="form.processing"
                    :label="trans('site_settings.save_changes')"
                />
            </UPageCard>

            <UPageCard
                variant="subtle"
                :title="trans('site_settings.theme.branding')"
            >
                <UFormField
                    :label="trans('site_settings.theme.logo')"
                    :help="trans('site_settings.theme.logo_help')"
                    :error="form.errors.logo_file_id"
                >
                    <FileField
                        v-model="form.logo_file_id"
                        images-only
                        :disabled="!canManage"
                    />
                </UFormField>

                <UFormField
                    :label="trans('site_settings.theme.favicon')"
                    :help="trans('site_settings.theme.favicon_help')"
                    :error="form.errors.favicon_file_id"
                >
                    <FileField
                        v-model="form.favicon_file_id"
                        images-only
                        :disabled="!canManage"
                    />
                </UFormField>
            </UPageCard>

            <UPageCard
                variant="subtle"
                :title="trans('site_settings.theme.admin_colors')"
                :description="
                    trans('site_settings.theme.admin_colors_description')
                "
            >
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <UFormField
                        :label="trans('site_settings.theme.primary_color')"
                        :error="form.errors.primary_color"
                        required
                    >
                        <USelect
                            v-model="form.primary_color"
                            :items="primaryItems"
                            :disabled="!canManage"
                            class="w-full"
                        >
                            <template #leading>
                                <span
                                    class="size-3 rounded-full"
                                    :style="{
                                        backgroundColor: swatch(
                                            form.primary_color,
                                        ),
                                    }"
                                />
                            </template>
                            <template #item-leading="{ item }">
                                <span
                                    class="size-3 rounded-full"
                                    :style="{
                                        backgroundColor: swatch(item.value),
                                    }"
                                />
                            </template>
                        </USelect>
                    </UFormField>

                    <UFormField
                        :label="trans('site_settings.theme.neutral_color')"
                        :error="form.errors.neutral_color"
                        required
                    >
                        <USelect
                            v-model="form.neutral_color"
                            :items="neutralItems"
                            :disabled="!canManage"
                            class="w-full"
                        >
                            <template #leading>
                                <span
                                    class="size-3 rounded-full"
                                    :style="{
                                        backgroundColor: swatch(
                                            form.neutral_color,
                                        ),
                                    }"
                                />
                            </template>
                            <template #item-leading="{ item }">
                                <span
                                    class="size-3 rounded-full"
                                    :style="{
                                        backgroundColor: swatch(item.value),
                                    }"
                                />
                            </template>
                        </USelect>
                    </UFormField>
                </div>
            </UPageCard>

            <UPageCard
                variant="subtle"
                :title="trans('site_settings.theme.site_colors')"
                :description="
                    trans('site_settings.theme.site_colors_description')
                "
            >
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <UFormField
                        v-for="(_, key) in defaultSiteColors"
                        :key="key"
                        :label="
                            trans(
                                `site_settings.theme.site_color_labels.${key}`,
                            )
                        "
                        :error="errors[`site_colors.${key}`]"
                    >
                        <UInput
                            v-model="form.site_colors[key]"
                            :disabled="!canManage"
                            :placeholder="defaultSiteColors[key]"
                            class="w-full font-mono"
                        >
                            <template #leading>
                                <span
                                    class="border-default size-4 rounded border"
                                    :style="{
                                        backgroundColor: validHex(
                                            form.site_colors[key],
                                        ),
                                    }"
                                />
                            </template>
                        </UInput>
                    </UFormField>
                </div>

                <UButton
                    v-if="canManage"
                    class="w-fit"
                    color="neutral"
                    variant="subtle"
                    icon="i-lucide-rotate-ccw"
                    :label="trans('site_settings.theme.reset_site_colors')"
                    @click="resetSiteColors"
                />
            </UPageCard>
        </form>
    </SiteSettingsSubLayout>
</template>
