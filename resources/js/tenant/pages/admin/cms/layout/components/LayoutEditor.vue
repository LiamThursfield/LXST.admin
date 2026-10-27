<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import {
    index,
    store,
    update,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Cms/LayoutController';
import ContentEditor from '@/tenant/components/cms/ContentEditor.vue';
import {
    type CrmOptions,
    provideCrmOptions,
    provideMenuOptions,
    provideNestedTemplates,
} from '@/tenant/components/cms/nestedTemplates';
import type { ContentValues, LayoutData, TemplateData } from '@/types';

const props = withDefaults(
    defineProps<{
        layout?: LayoutData;
        /** The layout templates to choose from, with their fields. */
        templates: TemplateData[];
        /** The component and repeater templates, with their fields, for nested content. */
        nestedTemplates: TemplateData[];
        /** The menus menu fields can choose from. */
        menus?: Array<{ value: number; label: string }>;
        /** The CRM forms and organisation units, or null without CRM. */
        crmOptions?: CrmOptions | null;
        readonly?: boolean;
    }>(),
    {
        layout: undefined,
        readonly: false,
    },
);

const { trans } = useTrans();

provideNestedTemplates(toRef(props, 'nestedTemplates'));
provideMenuOptions(toRef(() => props.menus ?? []));
provideCrmOptions(toRef(() => props.crmOptions ?? null));

const layout = props.layout;

const form = useForm({
    template_id: layout?.template_id ?? (null as number | null),
    name: layout?.name ?? '',
    slug: layout?.slug ?? '',
    content: { ...(layout?.content ?? {}) } as ContentValues,
});

// The content fields fill in empty values and key repeater rows as they mount, so count changes from there
onMounted(() => form.defaults());

/** Server validation errors, including nested ones such as `content.hero_title`. */
const errors = computed(
    () => form.errors as Record<string, string | undefined>,
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

/** Changing an existing layout's template drops content for fields the new one doesn't have. */
const templateChanged = computed(
    () => !!layout && form.template_id !== layout.template_id,
);

const title = computed(() => {
    if (!layout) {
        return trans('cms.layouts.create.title');
    }

    return props.readonly
        ? trans('cms.layouts.show.title', { name: layout.name })
        : trans('cms.layouts.edit.title', { name: layout.name });
});

const breadcrumbs = computed(() => [
    { label: trans('cms.layouts.index.title'), to: index.url() },
    { label: layout?.name ?? trans('cms.layouts.create.title') },
]);

function onSubmit() {
    if (props.readonly) return;

    form.submit(layout ? update({ layout: layout.id }) : store());
}
</script>

<template>
    <EditorLayout
        :title="title"
        :breadcrumbs="breadcrumbs"
        :is-dirty="form.isDirty"
        :processing="form.processing"
        :readonly="readonly"
        :submit-label="layout ? undefined : trans('cms.layouts.create.submit')"
        @submit="onSubmit"
    >
        <UPageCard
            variant="subtle"
            :title="trans('cms.layouts.sections.content')"
        >
            <ContentEditor
                v-if="template"
                v-model="form.content"
                :fields="template.fields ?? []"
                :errors="errors"
                :readonly="readonly"
            />
            <p v-else class="text-muted text-sm">
                {{ trans('cms.layouts.content_choose_template') }}
            </p>
        </UPageCard>

        <template #sidebar>
            <UPageCard
                variant="subtle"
                :title="trans('cms.layouts.sections.details')"
            >
                <div class="flex flex-col gap-4">
                    <UFormField
                        :label="trans('cms.layouts.fields.name')"
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
                        :label="trans('cms.layouts.fields.slug')"
                        :help="
                            readonly
                                ? undefined
                                : trans('cms.layouts.fields.slug_help')
                        "
                        :error="form.errors.slug"
                    >
                        <UInput
                            v-model="form.slug"
                            :disabled="readonly"
                            class="w-full font-mono"
                        />
                    </UFormField>

                    <UFormField
                        :label="trans('cms.layouts.fields.template')"
                        :help="
                            readonly
                                ? undefined
                                : templates.length
                                  ? trans('cms.layouts.fields.template_help')
                                  : trans('cms.layouts.fields.no_templates')
                        "
                        :error="form.errors.template_id"
                        required
                    >
                        <USelect
                            v-model.nullable="form.template_id"
                            :items="templateItems"
                            :disabled="readonly"
                            :placeholder="
                                trans('cms.layouts.fields.template_placeholder')
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
                            trans('cms.layouts.fields.template_changed')
                        "
                    />
                </div>
            </UPageCard>
        </template>
    </EditorLayout>
</template>
