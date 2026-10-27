<script setup lang="ts">
import { useForm, useHttp } from '@inertiajs/vue3';
import {
    index,
    store,
    update,
    urlCheck,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Cms/PageController';
import type { EditorTab } from '@/components/EditorLayout.vue';
import ContentEditor from '@/tenant/components/cms/ContentEditor.vue';
import {
    type CrmOptions,
    provideCrmOptions,
    provideMenuOptions,
    provideNestedTemplates,
} from '@/tenant/components/cms/nestedTemplates';
import FileField from '@/tenant/components/file-manager/FileField.vue';
import type {
    ContentValues,
    PageData,
    PageMetadata,
    PublishStatus,
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
        /** The CRM forms and organisation units, or null without CRM. */
        crmOptions?: CrmOptions | null;
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
provideCrmOptions(toRef(() => props.crmOptions ?? null));

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

// The content fields fill in empty values and key repeater rows as they mount, so count changes from there
onMounted(() => form.defaults());

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

const title = computed(() => {
    if (!page) {
        return trans('cms.pages.create.title');
    }

    return props.readonly
        ? trans('cms.pages.show.title', { name: page.name })
        : trans('cms.pages.edit.title', { name: page.name });
});

const breadcrumbs = computed(() => [
    { label: trans('cms.pages.index.title'), to: index.url() },
    { label: page?.name ?? trans('cms.pages.create.title') },
]);

const statusColors: Record<PublishStatus, 'success' | 'info' | 'neutral'> = {
    published: 'success',
    scheduled: 'info',
    expired: 'neutral',
    draft: 'neutral',
};

/** The metadata fields on each tab, for counting their errors. */
const seoFields = ['title', 'description', 'keywords', 'canonical'];

function errorCount(matches: (key: string) => boolean): number {
    return Object.keys(errors.value).filter(
        (key) => errors.value[key] && matches(key),
    ).length;
}

const tabs = computed<EditorTab[]>(() => [
    {
        value: 'content',
        label: trans('cms.pages.sections.content'),
        errorCount: errorCount((key) => key.startsWith('content.')),
    },
    {
        value: 'seo',
        label: trans('cms.pages.sections.metadata'),
        errorCount: errorCount((key) =>
            seoFields.some((field) => key === `metadata.${field}`),
        ),
    },
    {
        value: 'social',
        label: trans('cms.pages.sections.social'),
        errorCount: errorCount((key) => key.startsWith('metadata.og_')),
    },
]);

const tab = ref('content');

/** The recommended lengths shown beside the metadata title and description. */
const recommendedLength = { title: 60, description: 160 };

function lengthHint(value: string | null, max: number): string | undefined {
    return props.readonly
        ? undefined
        : trans('cms.pages.metadata.length', {
              count: (value ?? '').length,
              max,
          });
}

function onSubmit() {
    if (props.readonly) return;

    form.submit(page ? update({ page: page.id }) : store(), {
        // Show the first tab with errors, unless the current tab has some
        onError: () => {
            const withErrors = tabs.value.filter((item) => item.errorCount);

            if (
                withErrors.length &&
                !withErrors.some((item) => item.value === tab.value)
            ) {
                tab.value = withErrors[0].value;
            }
        },
    });
}
</script>

<template>
    <EditorLayout
        v-model:tab="tab"
        :title="title"
        :breadcrumbs="breadcrumbs"
        :tabs="tabs"
        :is-dirty="form.isDirty"
        :processing="form.processing"
        :readonly="readonly"
        :submit-label="page ? undefined : trans('cms.pages.create.submit')"
        @submit="onSubmit"
    >
        <template v-if="page?.status" #badges>
            <UBadge
                :color="statusColors[page.status]"
                variant="subtle"
                class="shrink-0"
            >
                {{ page.status_label }}
            </UBadge>
        </template>

        <UPageCard
            v-show="tab === 'content'"
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
            v-show="tab === 'seo'"
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
                    :hint="
                        lengthHint(form.metadata.title, recommendedLength.title)
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
                    :hint="
                        lengthHint(
                            form.metadata.description,
                            recommendedLength.description,
                        )
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

        <UPageCard
            v-show="tab === 'social'"
            variant="subtle"
            :title="trans('cms.pages.sections.social')"
        >
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

        <template #sidebar>
            <UPageCard
                variant="subtle"
                :title="trans('cms.pages.sections.details')"
            >
                <div class="flex flex-col gap-4">
                    <UFormField :label="trans('cms.pages.fields.url')">
                        <div class="flex min-h-8 items-center gap-2">
                            <span class="truncate font-mono text-sm">
                                {{ url?.url ?? page?.url ?? '' }}
                            </span>
                            <UBadge
                                v-if="url && !readonly"
                                :color="url.available ? 'success' : 'error'"
                                variant="subtle"
                                size="sm"
                                class="shrink-0"
                            >
                                {{
                                    url.available
                                        ? trans(
                                              'cms.pages.fields.url_available',
                                          )
                                        : trans('cms.pages.fields.url_taken')
                                }}
                            </UBadge>
                        </div>
                    </UFormField>

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

                    <UAlert
                        v-if="templateChanged"
                        color="warning"
                        variant="subtle"
                        icon="i-lucide-triangle-alert"
                        :description="
                            trans('cms.pages.fields.template_changed')
                        "
                    />

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
                </div>
            </UPageCard>

            <UPageCard
                variant="subtle"
                :title="trans('cms.pages.sections.publishing')"
            >
                <div class="flex flex-col gap-4">
                    <UFormField
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
        </template>
    </EditorLayout>
</template>
