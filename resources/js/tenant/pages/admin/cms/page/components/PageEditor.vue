<script setup lang="ts">
import { useForm, useHttp } from '@inertiajs/vue3';
import {
    store,
    update,
    urlCheck,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Cms/PageController';
import ContentEditor from '@/tenant/components/cms/ContentEditor.vue';
import {
    provideMenuOptions,
    provideNestedTemplates,
} from '@/tenant/components/cms/nestedTemplates';
import FileField from '@/tenant/components/file-manager/FileField.vue';
import type {
    ContentValues,
    PageData,
    PageMetadata,
    TemplateData,
} from '@/types';

const props = withDefaults(
    defineProps<{
        page?: PageData;
        /** The page templates to choose from, with their fields. */
        templates: TemplateData[];
        /** The component and repeater templates, with their fields, for nested content. */
        nestedTemplates: TemplateData[];
        /** The menus menu fields can choose from. */
        menus?: Array<{ value: number; label: string }>;
        layouts: Array<{ value: number; label: string }>;
        /** The pages this one can sit under. */
        parents: Array<{ value: number; label: string }>;
        defaultParentId?: number | null;
        readonly?: boolean;
    }>(),
    {
        page: undefined,
        defaultParentId: null,
        readonly: false,
    },
);

const { trans } = useTrans();

provideNestedTemplates(toRef(props, 'nestedTemplates'));
provideMenuOptions(toRef(() => props.menus ?? []));

const page = props.page;

const emptyMetadata: PageMetadata = {
    title: null,
    description: null,
    keywords: null,
    canonical: null,
    og_title: null,
    og_description: null,
    og_type: null,
    og_url: null,
    og_image_file_id: null,
};

const form = useForm({
    name: page?.name ?? '',
    parent_id: page?.parent_id ?? props.defaultParentId,
    slug: page?.slug ?? '',
    template_id: page?.template_id ?? (null as number | null),
    layout_id: page?.layout_id ?? (null as number | null),
    is_enabled: page?.is_enabled ?? false,
    published_at: page?.published_at_local ?? '',
    expired_at: page?.expired_at_local ?? '',
    content: { ...(page?.content ?? {}) } as ContentValues,
    metadata: { ...emptyMetadata, ...(page?.metadata ?? {}) } as PageMetadata,
});

/** Server validation errors, including nested ones such as `content.hero_title`. */
const errors = computed(
    () => form.errors as Record<string, string | undefined>,
);

// A new page's slug follows its name until the slug is edited
const slugEdited = ref(!!page);

watch(
    () => form.name,
    (name) => {
        if (!slugEdited.value) {
            form.slug = name
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '');
        }
    },
);

const templateItems = computed(() =>
    props.templates.map((template) => ({
        label: template.name,
        value: template.id,
    })),
);

const template = computed(() =>
    props.templates.find((item) => item.id === form.template_id),
);

const templateChanged = computed(
    () => !!page && form.template_id !== page.template_id,
);

/** The page's URL, checked as the slug or parent changes. */
const urlStatus = useHttp();
const url = ref<{ url: string; available: boolean } | null>(
    page?.url ? { url: page.url, available: true } : null,
);

const checkUrl = useDebounceFn(async () => {
    try {
        url.value = (await urlStatus.get(
            urlCheck.url({
                query: {
                    slug: form.slug,
                    ...(form.parent_id
                        ? { parent_id: String(form.parent_id) }
                        : {}),
                    ...(page ? { page_id: String(page.id) } : {}),
                },
            }),
        )) as { url: string; available: boolean };
    } catch {
        url.value = null;
    }
}, 300);

watch(
    () => [form.slug, form.parent_id],
    () => {
        if (!props.readonly) {
            checkUrl();
        }
    },
    { immediate: !page },
);

function onSubmit() {
    if (props.readonly) return;

    form.submit(page ? update({ page: page.id }) : store());
}
</script>

<template>
    <form class="space-y-6" @submit.prevent="onSubmit">
        <UPageCard
            variant="subtle"
            :title="trans('cms.pages.sections.details')"
        >
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <UFormField
                    :label="trans('cms.pages.fields.name')"
                    :error="form.errors.name"
                    required
                >
                    <UInput
                        v-model="form.name"
                        :disabled="readonly"
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    :label="trans('cms.pages.fields.parent')"
                    :error="form.errors.parent_id"
                >
                    <USelect
                        v-model.nullable="form.parent_id"
                        :items="parents"
                        :disabled="readonly"
                        :placeholder="
                            trans('cms.pages.fields.parent_placeholder')
                        "
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    :label="trans('cms.pages.fields.slug')"
                    :help="
                        readonly
                            ? undefined
                            : trans('cms.pages.fields.slug_help')
                    "
                    :error="form.errors.slug"
                >
                    <UInput
                        v-model="form.slug"
                        :disabled="readonly"
                        class="w-full font-mono"
                        @update:model-value="slugEdited = true"
                    />
                </UFormField>

                <UFormField :label="trans('cms.pages.fields.url')">
                    <div class="flex h-8 items-center gap-2">
                        <span class="truncate font-mono text-sm">
                            {{ url?.url ?? page?.url ?? '' }}
                        </span>
                        <UBadge
                            v-if="url && !readonly"
                            :color="url.available ? 'success' : 'error'"
                            variant="subtle"
                            size="sm"
                        >
                            {{
                                url.available
                                    ? trans('cms.pages.fields.url_available')
                                    : trans('cms.pages.fields.url_taken')
                            }}
                        </UBadge>
                    </div>
                </UFormField>

                <UFormField
                    :label="trans('cms.pages.fields.template')"
                    :help="
                        readonly || templates.length
                            ? undefined
                            : trans('cms.pages.fields.no_templates')
                    "
                    :error="form.errors.template_id"
                    required
                >
                    <USelect
                        v-model.nullable="form.template_id"
                        :items="templateItems"
                        :disabled="readonly"
                        :placeholder="
                            trans('cms.pages.fields.template_placeholder')
                        "
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    :label="trans('cms.pages.fields.layout')"
                    :help="
                        readonly || layouts.length
                            ? undefined
                            : trans('cms.pages.fields.no_layouts')
                    "
                    :error="form.errors.layout_id"
                    required
                >
                    <USelect
                        v-model.nullable="form.layout_id"
                        :items="layouts"
                        :disabled="readonly"
                        :placeholder="
                            trans('cms.pages.fields.layout_placeholder')
                        "
                        class="w-full"
                    />
                </UFormField>

                <UAlert
                    v-if="templateChanged"
                    class="md:col-span-2"
                    color="warning"
                    variant="subtle"
                    icon="i-lucide-triangle-alert"
                    :description="trans('cms.pages.fields.template_changed')"
                />
            </div>
        </UPageCard>

        <UPageCard
            variant="subtle"
            :title="trans('cms.pages.sections.publishing')"
        >
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <UFormField
                    class="md:col-span-2"
                    :help="
                        readonly
                            ? undefined
                            : trans('cms.pages.fields.is_enabled_help')
                    "
                    :error="form.errors.is_enabled"
                >
                    <USwitch
                        v-model="form.is_enabled"
                        :disabled="readonly"
                        :label="trans('cms.pages.fields.is_enabled')"
                    />
                </UFormField>

                <UFormField
                    :label="trans('cms.pages.fields.published_at')"
                    :help="
                        readonly
                            ? undefined
                            : trans('cms.pages.fields.published_at_help')
                    "
                    :error="form.errors.published_at"
                >
                    <UInput
                        v-model="form.published_at"
                        type="datetime-local"
                        :disabled="readonly"
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    :label="trans('cms.pages.fields.expired_at')"
                    :help="
                        readonly
                            ? undefined
                            : trans('cms.pages.fields.expired_at_help')
                    "
                    :error="form.errors.expired_at"
                >
                    <UInput
                        v-model="form.expired_at"
                        type="datetime-local"
                        :disabled="readonly"
                        class="w-full"
                    />
                </UFormField>
            </div>
        </UPageCard>

        <UPageCard
            variant="subtle"
            :title="trans('cms.pages.sections.content')"
        >
            <ContentEditor
                v-if="template"
                v-model="form.content"
                :fields="template.fields ?? []"
                :errors="errors"
                :readonly="readonly"
            />
            <p v-else class="text-muted text-sm">
                {{ trans('cms.pages.content_choose_template') }}
            </p>
        </UPageCard>

        <UPageCard
            variant="subtle"
            :title="trans('cms.pages.sections.metadata')"
        >
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <UFormField
                    class="md:col-span-2"
                    :label="trans('cms.pages.metadata.title')"
                    :help="
                        readonly
                            ? undefined
                            : trans('cms.pages.metadata.title_help')
                    "
                    :error="errors['metadata.title']"
                >
                    <UInput
                        v-model.nullable="form.metadata.title"
                        :disabled="readonly"
                        maxlength="255"
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    class="md:col-span-2"
                    :label="trans('cms.pages.metadata.description')"
                    :help="
                        readonly
                            ? undefined
                            : trans('cms.pages.metadata.description_help')
                    "
                    :error="errors['metadata.description']"
                >
                    <UTextarea
                        v-model.nullable="form.metadata.description"
                        :disabled="readonly"
                        :rows="2"
                        maxlength="255"
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    :label="trans('cms.pages.metadata.keywords')"
                    :error="errors['metadata.keywords']"
                >
                    <UInput
                        v-model.nullable="form.metadata.keywords"
                        :disabled="readonly"
                        maxlength="255"
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    :label="trans('cms.pages.metadata.canonical')"
                    :help="
                        readonly
                            ? undefined
                            : trans('cms.pages.metadata.canonical_help')
                    "
                    :error="errors['metadata.canonical']"
                >
                    <UInput
                        v-model.nullable="form.metadata.canonical"
                        :disabled="readonly"
                        maxlength="255"
                        class="w-full"
                    />
                </UFormField>
            </div>
        </UPageCard>

        <UPageCard variant="subtle" :title="trans('cms.pages.sections.social')">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <UFormField
                    class="md:col-span-2"
                    :label="trans('cms.pages.metadata.og_image')"
                    :help="
                        readonly
                            ? undefined
                            : trans('cms.pages.metadata.og_image_help')
                    "
                    :error="errors['metadata.og_image_file_id']"
                >
                    <FileField
                        v-model="form.metadata.og_image_file_id"
                        images-only
                        :disabled="readonly"
                    />
                </UFormField>

                <UFormField
                    :label="trans('cms.pages.metadata.og_title')"
                    :error="errors['metadata.og_title']"
                >
                    <UInput
                        v-model.nullable="form.metadata.og_title"
                        :disabled="readonly"
                        maxlength="255"
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    :label="trans('cms.pages.metadata.og_type')"
                    :error="errors['metadata.og_type']"
                >
                    <UInput
                        v-model.nullable="form.metadata.og_type"
                        :disabled="readonly"
                        maxlength="50"
                        :placeholder="
                            trans('cms.pages.metadata.og_type_placeholder')
                        "
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    class="md:col-span-2"
                    :label="trans('cms.pages.metadata.og_description')"
                    :error="errors['metadata.og_description']"
                >
                    <UTextarea
                        v-model.nullable="form.metadata.og_description"
                        :disabled="readonly"
                        :rows="2"
                        maxlength="255"
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    class="md:col-span-2"
                    :label="trans('cms.pages.metadata.og_url')"
                    :error="errors['metadata.og_url']"
                >
                    <UInput
                        v-model.nullable="form.metadata.og_url"
                        :disabled="readonly"
                        maxlength="255"
                        class="w-full"
                    />
                </UFormField>
            </div>
        </UPageCard>

        <div v-if="!readonly" class="flex justify-end gap-2">
            <UButton
                type="submit"
                color="primary"
                :loading="form.processing"
                :label="
                    page
                        ? trans('cms.pages.edit.submit')
                        : trans('cms.pages.create.submit')
                "
            />
        </div>
    </form>
</template>
