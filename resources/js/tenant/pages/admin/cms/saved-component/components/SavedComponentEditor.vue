<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import {
    index,
    store,
    update,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Cms/SavedComponentController';
import TextLink from '@/components/TextLink.vue';
import ContentEditor from '@/tenant/components/cms/ContentEditor.vue';
import { provideNestedTemplates } from '@/tenant/components/cms/nestedTemplates';
import type {
    ContentValues,
    SavedComponentData,
    SavedComponentUsage,
    TemplateData,
} from '@/types';

const props = withDefaults(
    defineProps<{
        savedComponent?: SavedComponentData;
        /** The component templates to choose from, with their fields. */
        templates: TemplateData[];
        /** The component and repeater templates, with their fields, for nested content. */
        nestedTemplates: TemplateData[];
        /** The pages and layouts using it. */
        usage?: SavedComponentUsage[];
        readonly?: boolean;
    }>(),
    {
        savedComponent: undefined,
        usage: () => [],
        readonly: false,
    },
);

const { trans, transChoice } = useTrans();

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

const isInUse = computed(() => props.usage.length > 0);

/** Where it's used, e.g. "4 pages and 1 layout". */
const places = computed(() => {
    const count = (type: SavedComponentUsage['type']) =>
        props.usage.filter((item) => item.type === type).length;
    const pages = count('page');
    const layouts = count('layout');

    if (pages && layouts) {
        return trans('cms.saved_components.usage.and', {
            first: transChoice('cms.saved_components.usage.pages', pages),
            second: transChoice('cms.saved_components.usage.layouts', layouts),
        });
    }

    return pages
        ? transChoice('cms.saved_components.usage.pages', pages)
        : transChoice('cms.saved_components.usage.layouts', layouts);
});

const confirmOpen = ref(false);

/** Edits go live straight away everywhere it's used, so check first. */
function onSubmit() {
    if (props.readonly) return;

    if (isInUse.value) {
        confirmOpen.value = true;

        return;
    }

    save();
}

function save() {
    confirmOpen.value = false;

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
                                : isInUse
                                  ? trans(
                                        'cms.saved_components.usage.template_locked',
                                    )
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
                            :disabled="readonly || isInUse"
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

            <UPageCard
                v-if="savedComponent"
                variant="subtle"
                :title="trans('cms.saved_components.usage.title')"
            >
                <p v-if="!isInUse" class="text-muted text-sm">
                    {{ trans('cms.saved_components.usage.unused') }}
                </p>
                <div v-else class="flex flex-col gap-3 text-sm">
                    <p class="text-highlighted font-medium">
                        {{
                            trans('cms.saved_components.usage.used_on', {
                                places,
                            })
                        }}
                    </p>
                    <ul class="flex flex-col gap-1.5">
                        <li
                            v-for="item in usage"
                            :key="`${item.type}-${item.name}`"
                            class="flex items-baseline justify-between gap-2"
                        >
                            <TextLink v-if="item.url" :href="item.url">
                                {{ item.name }}
                            </TextLink>
                            <span v-else>{{ item.name }}</span>
                            <span class="text-muted text-xs">
                                {{
                                    trans(
                                        `cms.saved_components.usage.type.${item.type}`,
                                    )
                                }}
                            </span>
                        </li>
                    </ul>
                </div>
            </UPageCard>
        </template>

        <UModal
            v-model:open="confirmOpen"
            :title="trans('cms.saved_components.usage.confirm_title')"
            :description="
                trans('cms.saved_components.usage.confirm_message', { places })
            "
        >
            <template #footer>
                <div class="flex w-full justify-end gap-2">
                    <UButton
                        color="neutral"
                        variant="subtle"
                        :label="trans('cms.saved_components.usage.cancel')"
                        @click="confirmOpen = false"
                    />
                    <UButton
                        :loading="form.processing"
                        :label="trans('cms.saved_components.usage.confirm')"
                        @click="save"
                    />
                </div>
            </template>
        </UModal>
    </EditorLayout>
</template>
