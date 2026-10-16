<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import {
    store,
    update,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Cms/LayoutController';
import ContentEditor from '@/tenant/components/cms/ContentEditor.vue';
import {
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

const layout = props.layout;

const form = useForm({
    template_id: layout?.template_id ?? (null as number | null),
    name: layout?.name ?? '',
    slug: layout?.slug ?? '',
    content: { ...(layout?.content ?? {}) } as ContentValues,
});

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

function onSubmit() {
    if (props.readonly) return;

    form.submit(layout ? update({ layout: layout.id }) : store());
}
</script>

<template>
    <form class="space-y-6" @submit.prevent="onSubmit">
        <UPageCard
            variant="subtle"
            :title="trans('cms.layouts.sections.details')"
        >
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
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
                    class="md:col-span-2"
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
                        v-model="form.template_id"
                        :items="templateItems"
                        :disabled="readonly"
                        :placeholder="
                            trans('cms.layouts.fields.template_placeholder')
                        "
                        class="w-full md:w-1/2"
                    />
                </UFormField>

                <UAlert
                    v-if="templateChanged"
                    class="md:col-span-2"
                    color="warning"
                    variant="subtle"
                    icon="i-lucide-triangle-alert"
                    :description="trans('cms.layouts.fields.template_changed')"
                />
            </div>
        </UPageCard>

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

        <div v-if="!readonly" class="flex justify-end gap-2">
            <UButton
                type="submit"
                color="primary"
                :loading="form.processing"
                :label="
                    layout
                        ? trans('cms.layouts.edit.submit')
                        : trans('cms.layouts.create.submit')
                "
            />
        </div>
    </form>
</template>
