<script setup lang="ts">
import type { Component } from 'vue';
import type {
    ContentValues,
    TemplateFieldData,
    TemplateSectionData,
} from '@/types';
import ContentSection from './ContentSection.vue';
import {
    provideContentState,
    revealErrors,
    setAllOpen,
    useContentState,
} from './contentState';
import { opening, richTextWords, textValues } from './contentSummary';
import ComponentContentField from './fields/ComponentContentField.vue';
import CrmFormContentField from './fields/CrmFormContentField.vue';
import CrmOrganisationUnitContentField from './fields/CrmOrganisationUnitContentField.vue';
import ImageContentField from './fields/ImageContentField.vue';
import MenuContentField from './fields/MenuContentField.vue';
import NumberContentField from './fields/NumberContentField.vue';
import RepeaterContentField from './fields/RepeaterContentField.vue';
import RichTextContentField from './fields/RichTextContentField.vue';
import TextareaContentField from './fields/TextareaContentField.vue';
import TextContentField from './fields/TextContentField.vue';
import { nestedTemplatesKey } from './nestedTemplates';

/**
 * Edits content for a template's fields (layouts, and pages in #51). Values are keyed by field
 * slug, and server errors are expected under the prefix, e.g. `content.{slug}`. Components and
 * repeaters nest another ContentEditor with a longer prefix, e.g. `content.slides.0`.
 *
 * The top editor is `sectioned`. When the template has sections, the fields with no section share
 * a card and each section gets a collapsible card of its own. Without sections, its simple fields
 * share a card, and each component, repeater and rich text field gets one of its own. It keeps
 * which cards and repeater rows are open, and after a failed save opens the ones with errors and
 * scrolls to the first. Nested editors show sections as headed groups.
 *
 * Half-width fields sit side by side when the editor is wide enough.
 */
const props = withDefaults(
    defineProps<{
        fields: TemplateFieldData[];
        /** The template's sections, which fields are grouped in by `section_id`. */
        sections?: TemplateSectionData[];
        prefix?: string;
        /** Where this editor's open cards and rows are remembered; rows use their `_key`, not their index. */
        stateKey?: string;
        errors?: Record<string, string | undefined>;
        readonly?: boolean;
        sectioned?: boolean;
    }>(),
    {
        sections: () => [],
        prefix: 'content',
        stateKey: undefined,
        errors: () => ({}),
        readonly: false,
        sectioned: false,
    },
);

const values = defineModel<ContentValues>({ required: true });

const { trans, transChoice } = useTrans();

const state = props.sectioned ? provideContentState() : useContentState();

const nestedTemplates = inject(nestedTemplatesKey, ref([]));

const baseStateKey = computed(() => props.stateKey ?? props.prefix);

/** The editor for each field type. */
const editors: Record<string, Component> = {
    text: TextContentField,
    textarea: TextareaContentField,
    number: NumberContentField,
    wysiwyg: RichTextContentField,
    image: ImageContentField,
    component: ComponentContentField,
    repeater: RepeaterContentField,
    cms_menu: MenuContentField,
    crm_form: CrmFormContentField,
    crm_organisation_unit: CrmOrganisationUnitContentField,
};

/** Components and repeaters show their own fields' errors, so they get the errors and their prefix. */
const nestedTypes = ['component', 'repeater'];

/** In a sectioned editor, these get a card of their own rather than sharing one. */
const ownSectionTypes = [...nestedTypes, 'wysiwyg'];

function nestedProps(field: TemplateFieldData) {
    if (!nestedTypes.includes(field.type)) {
        return {};
    }

    return {
        prefix: `${props.prefix}.${field.slug}`,
        stateKey: `${baseStateKey.value}.${field.slug}`,
        errors: props.errors,
        // A component in a card of its own doesn't need a border as well
        ...(field.type === 'component' ? { framed: !props.sectioned } : {}),
    };
}

/**
 * The first error for a field, including nested ones like an image's `content.hero.file_id`.
 * Components and repeaters only show their own (e.g. too few rows); their fields show the rest.
 */
function errorFor(field: TemplateFieldData): string | undefined {
    const key = `${props.prefix}.${field.slug}`;

    if (props.errors[key] || nestedTypes.includes(field.type)) {
        return props.errors[key];
    }

    return firstErrorUnder(key);
}

/** The first error for the key or anything nested in it, e.g. a repeater row's field. */
function firstErrorUnder(key: string): string | undefined {
    return Object.entries(props.errors).find(
        ([errorKey, message]) =>
            message && (errorKey === key || errorKey.startsWith(`${key}.`)),
    )?.[1];
}

function editorFor(field: TemplateFieldData): Component | undefined {
    return editors[field.type];
}

const hasFields = computed(() => props.fields.length > 0);

/**
 * A group of fields and how it's shown: a collapsible card, a plain card (fields with no section,
 * above the sections), a heading (a section in a nested editor), or just the fields.
 */
type FieldGroup = {
    key: string;
    fields: TemplateFieldData[];
    /** Fields in their own card are titled by the card, so they go without a label. */
    labelled: boolean;
    kind: 'section' | 'card' | 'heading' | 'bare';
    section?: InstanceType<typeof ContentSection>['$props'];
    heading?: { title: string; description: string | null };
};

/** The template's sections that have fields, in order. */
const usedSections = computed(() =>
    props.sections.filter((section) =>
        props.fields.some((field) => field.section_id === section.id),
    ),
);

function firstErrorIn(fields: TemplateFieldData[]): string | undefined {
    return fields
        .map((field) => firstErrorUnder(`${props.prefix}.${field.slug}`))
        .find(Boolean);
}

/** What a collapsed card of several fields says is in it: its first text, or how many fields. */
function fieldsSummary(fields: TemplateFieldData[]): string {
    return (
        textValues(fields, values.value)[0] ??
        transChoice('cms.content.summary.fields', fields.length)
    );
}

const groups = computed<FieldGroup[]>(() => {
    if (usedSections.value.length) {
        return sectionGroups();
    }

    if (!props.sectioned) {
        return [
            {
                key: 'fields',
                fields: props.fields,
                labelled: true,
                kind: 'bare',
            },
        ];
    }

    const shared = props.fields.filter(
        (field) => !ownSectionTypes.includes(field.type),
    );
    const own = props.fields.filter((field) =>
        ownSectionTypes.includes(field.type),
    );

    return [
        ...(shared.length
            ? [
                  {
                      key: 'fields',
                      fields: shared,
                      labelled: true,
                      kind: 'section' as const,
                      section: {
                          title: trans('cms.content.sections.fields'),
                          stateKey: `${baseStateKey.value}:fields`,
                          summary: fieldsSummary(shared),
                          error: firstErrorIn(shared),
                      },
                  },
              ]
            : []),
        ...own.map((field) => ({
            key: field.slug,
            fields: [field],
            labelled: false,
            kind: 'section' as const,
            section: {
                title: field.name,
                stateKey: `${baseStateKey.value}.${field.slug}`,
                description: field.description,
                summary: summaryFor(field),
                error: firstErrorUnder(`${props.prefix}.${field.slug}`),
                required: field.is_required,
            },
        })),
    ];
});

/** The fields with no section first, then a group for each of the template's sections. */
function sectionGroups(): FieldGroup[] {
    const sectionIds = usedSections.value.map((section) => section.id);
    const unsectioned = props.fields.filter(
        (field) => !sectionIds.includes(field.section_id ?? null),
    );

    return [
        ...(unsectioned.length
            ? [
                  {
                      key: 'fields',
                      fields: unsectioned,
                      labelled: true,
                      kind: props.sectioned
                          ? ('card' as const)
                          : ('bare' as const),
                  },
              ]
            : []),
        ...usedSections.value.map((section) => {
            const fields = props.fields.filter(
                (field) => field.section_id === section.id,
            );

            return props.sectioned
                ? {
                      key: `section-${section.id}`,
                      fields,
                      labelled: true,
                      kind: 'section' as const,
                      section: {
                          title: section.name,
                          stateKey: `${baseStateKey.value}:section-${section.id}`,
                          description: section.description,
                          summary: fieldsSummary(fields),
                          error: firstErrorIn(fields),
                          openByDefault: !section.is_collapsed,
                      },
                  }
                : {
                      key: `section-${section.id}`,
                      fields,
                      labelled: true,
                      kind: 'heading' as const,
                      heading: {
                          title: section.name,
                          description: section.description,
                      },
                  };
        }),
    ];
}

/** The props for a group's wrapper, by how it's shown. */
function wrapperFor(group: FieldGroup) {
    if (group.kind === 'section') {
        return { is: ContentSection, props: group.section };
    }

    return {
        is: 'div',
        props: {
            class:
                group.kind === 'card'
                    ? 'bg-elevated/50 ring-default rounded-lg p-4 ring sm:p-6'
                    : 'flex flex-col gap-3',
        },
    };
}

/** Half-width fields share a row; a field in a card of its own always takes the full width. */
function isHalfWidth(field: TemplateFieldData, group: FieldGroup): boolean {
    return group.labelled && field.settings.width === 'half';
}

/** What a collapsed card for a component, repeater or rich text field says is in it. */
function summaryFor(field: TemplateFieldData): string {
    const value = values.value[field.slug];
    const empty = trans('cms.content.summary.empty');

    if (field.type === 'repeater') {
        const count = Array.isArray(value) ? value.length : 0;
        const max = field.settings.max_items;

        return max
            ? trans('cms.content.summary.items_of', { count, max })
            : transChoice('cms.content.summary.items', count);
    }

    if (field.type === 'wysiwyg') {
        const words = richTextWords(value);

        return words.length
            ? trans('cms.content.summary.rich_text', {
                  words: transChoice('cms.content.summary.words', words.length),
                  opening: opening(words),
              })
            : empty;
    }

    const template = nestedTemplates.value.find(
        (item) => item.id === field.settings.template_id,
    );

    return textValues(template?.fields ?? [], value)[0] ?? empty;
}

const overview = computed(() =>
    [
        transChoice('cms.content.summary.sections', groups.value.length),
        transChoice('cms.content.summary.fields', props.fields.length),
    ].join(' · '),
);

const root = useTemplateRef<HTMLElement>('root');

// After a failed save, open the cards and rows with errors, and scroll to the first
watch(
    () => props.errors,
    async (errors) => {
        const hasContentErrors = Object.entries(errors).some(
            ([key, message]) => message && key.startsWith(`${props.prefix}.`),
        );

        if (!props.sectioned || !hasContentErrors) {
            return;
        }

        revealErrors(state);
        await nextTick();

        const marked = [
            ...(root.value?.querySelectorAll('[data-content-error]') ?? []),
        ];

        marked
            .find((element) => !element.querySelector('[data-content-error]'))
            ?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    },
);
</script>

<template>
    <div ref="root" class="flex flex-col gap-4">
        <p v-if="!hasFields" class="text-muted text-sm">
            {{ trans('cms.content.empty') }}
        </p>

        <div
            v-else-if="sectioned"
            class="flex flex-wrap items-center justify-between gap-2"
        >
            <p class="text-muted text-sm">{{ overview }}</p>
            <div class="flex gap-1">
                <UButton
                    color="neutral"
                    variant="ghost"
                    size="sm"
                    :label="trans('cms.content.expand_all')"
                    @click="setAllOpen(state, true)"
                />
                <UButton
                    color="neutral"
                    variant="ghost"
                    size="sm"
                    :label="trans('cms.content.collapse_all')"
                    @click="setAllOpen(state, false)"
                />
            </div>
        </div>

        <component
            :is="wrapperFor(group).is"
            v-for="group in groups"
            :key="group.key"
            v-bind="wrapperFor(group).props"
        >
            <div v-if="group.heading">
                <h3 class="text-highlighted font-semibold">
                    {{ group.heading.title }}
                </h3>
                <p v-if="group.heading.description" class="text-muted text-sm">
                    {{ group.heading.description }}
                </p>
            </div>

            <div class="@container">
                <div class="@xl:grid-cols-2 grid grid-cols-1 gap-4">
                    <UFormField
                        v-for="field in group.fields"
                        :key="field.slug"
                        :class="{
                            '@xl:col-span-2': !isHalfWidth(field, group),
                        }"
                        :label="group.labelled ? field.name : undefined"
                        :help="
                            group.labelled
                                ? (field.description ?? undefined)
                                : undefined
                        "
                        :error="errorFor(field)"
                        :required="group.labelled && field.is_required"
                    >
                        <component
                            :is="editorFor(field)"
                            v-if="editorFor(field)"
                            v-model="values[field.slug]"
                            :field="field"
                            :disabled="readonly"
                            v-bind="nestedProps(field)"
                        />
                        <p v-else class="text-muted text-sm">
                            {{
                                trans('cms.content.unsupported', {
                                    type: field.type_label ?? field.type,
                                })
                            }}
                        </p>
                    </UFormField>
                </div>
            </div>
        </component>
    </div>
</template>
