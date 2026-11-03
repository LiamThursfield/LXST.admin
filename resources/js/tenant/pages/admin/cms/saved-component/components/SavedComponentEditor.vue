<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import {
    index,
    store,
    update,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Cms/SavedComponentController';
import ContentEditor from '@/tenant/components/cms/ContentEditor.vue';
import { provideNestedTemplates } from '@/tenant/components/cms/nestedTemplates';
import type { ContentValues, SavedComponentData, TemplateData } from '@/types';

const props = withDefaults(
    defineProps<{
        savedComponent?: SavedComponentData;
        /** The component templates to choose from, with their fields. */
        templates: TemplateData[];
        /** The component and repeater templates, with their fields, for nested content. */
        nestedTemplates: TemplateData[];
        readonly?: boolean;
    }>(),
    {
        savedComponent: undefined,
        readonly: false,
    },
);

const { trans } = useTrans();

provideNestedTemplates(toRef(props, 'nestedTemplates'));

const savedComponent = props.savedComponent;

const form = useForm({
    template_id: savedComponent?.template_id ?? (null as number | null),
    name: savedComponent?.name ?? '',
    slug: savedComponent?.slug ?? '',
    content: { ...(savedComponent?.content ?? {}) } as ContentValues,
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

/** Changing an existing saved component's template drops content for fields the new one doesn't have. */
const templateChanged = computed(
    () => !!savedComponent && form.template_id !== savedComponent.template_id,
);

const title = computed(() => {
    if (!savedComponent) {
        return trans('cms.saved_components.create.title');
    }

    return props.readonly
        ? trans('cms.saved_components.show.title', {
              name: savedComponent.name,
          })
        : trans('cms.saved_components.edit.title', {
              name: savedComponent.name,
          });
});

const breadcrumbs = computed(() => [
    { label: trans('cms.saved_components.index.title'), to: index.url() },
    {
        label:
            savedComponent?.name ?? trans('cms.saved_components.create.title'),
    },
]);

function onSubmit() {
    if (props.readonly) return;

    form.submit(
        savedComponent
            ? update({ saved_component: savedComponent.id })
            : store(),
    );
}
</script>

<template>
    <EditorLayout
        :title="title"
        :breadcrumbs="breadcrumbs"
        :is-dirty="form.isDirty"
        :processing="form.processing"
        :readonly="readonly"
        :submit-label="
            savedComponent
                ? undefined
                : trans('cms.saved_components.create.submit')
        "
        @submit="onSubmit"
    >
        <ContentEditor
            v-if="template"
            v-model="form.content"
            :fields="template.fields ?? []"
            :sections="template.sections ?? []"
            :errors="errors"
            :readonly="readonly"
            sectioned
            :title="form.name"
        />
        <UPageCard
            v-else
            variant="subtle"
            :title="trans('cms.saved_components.sections.content')"
        >
            <p class="text-muted text-sm">
                {{ trans('cms.saved_components.content_choose_template') }}
            </p>
        </UPageCard>

        <template #sidebar>
            <UPageCard
                variant="subtle"
                :title="trans('cms.saved_components.sections.details')"
            >
                <div class="flex flex-col gap-4">
                    <UFormField
                        :label="trans('cms.saved_components.fields.name')"
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
                        :label="trans('cms.saved_components.fields.slug')"
                        :help="
                            readonly
                                ? undefined
                                : trans('cms.saved_components.fields.slug_help')
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
                        :label="trans('cms.saved_components.fields.template')"
                        :help="
                            readonly
                                ? undefined
                                : templates.length
                                  ? trans(
                                        'cms.saved_components.fields.template_help',
                                    )
                                  : trans(
                                        'cms.saved_components.fields.no_templates',
                                    )
                        "
                        :error="form.errors.template_id"
                        required
                    >
                        <USelect
                            v-model.nullable="form.template_id"
                            :items="templateItems"
                            :disabled="readonly"
                            :placeholder="
                                trans(
                                    'cms.saved_components.fields.template_placeholder',
                                )
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
                            trans(
                                'cms.saved_components.fields.template_changed',
                            )
                        "
                    />
                </div>
            </UPageCard>
        </template>
    </EditorLayout>
</template>
