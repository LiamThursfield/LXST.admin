<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { VueDraggable } from 'vue-draggable-plus';
import {
    index,
    show,
    store,
    update,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Catalogue/CatalogueController';
import { catalogueItemPrice } from '@/lib/catalogue';
import CatalogueItemFields from '@/tenant/components/catalogue/CatalogueItemFields.vue';
import CatalogueTagList from '@/tenant/components/catalogue/CatalogueTagList.vue';
import type {
    CatalogueData,
    CatalogueItemData,
    CatalogueSectionData,
    CatalogueServiceOption,
    CatalogueTagOption,
} from '@/types';

/**
 * Creating or editing a catalogue: its details and when it applies in the sidebar, and its sections
 * and items in the main column. Sections and items can be dragged to reorder them, and items into
 * another section. An item opens in a panel to edit it. Everything is saved together.
 */
const props = defineProps<{
    catalogue?: { data: CatalogueData };
    tags: CatalogueTagOption[];
    services: CatalogueServiceOption[] | null;
    days: Array<{ value: number; label: string }>;
    currency: string;
}>();

const { trans } = useTrans();

const dayItems = computed(() =>
    props.days.map((day) => ({ value: String(day.value), label: day.label })),
);

type EditorItem = CatalogueItemData & { _key: number };
type EditorSection = Omit<CatalogueSectionData, 'items'> & {
    _key: number;
    items: EditorItem[];
};

let nextKey = 0;

function editorItem(item: CatalogueItemData): EditorItem {
    return {
        ...item,
        variations: item.variations.map((variation) => ({ ...variation })),
        tag_ids: [...item.tag_ids],
        _key: nextKey++,
    };
}

const existing = props.catalogue?.data;

const form = useForm({
    name: existing?.name ?? '',
    slug: existing?.slug ?? '',
    description: existing?.description ?? '',
    is_published: existing?.is_published ?? false,
    // As strings, as checkbox groups use string values
    available_days: (existing?.available_days ?? []).map(String),
    available_from: existing?.available_from ?? '',
    available_until: existing?.available_until ?? '',
    starts_on: existing?.starts_on ?? '',
    ends_on: existing?.ends_on ?? '',
    sections: (existing?.sections ?? []).map((section): EditorSection => ({
        id: section.id,
        name: section.name,
        description: section.description,
        items: section.items.map(editorItem),
        _key: nextKey++,
    })),
});

/** Server validation errors, including nested ones such as `sections.0.items.1.name`. */
const errors = computed(
    () => form.errors as Record<string, string | undefined>,
);

const title = computed(() =>
    trans(
        existing
            ? 'catalogue.catalogues.edit.title'
            : 'catalogue.catalogues.create.title',
    ),
);

const breadcrumbs = computed(() => [
    { label: trans('catalogue.catalogues.index.title'), to: index.url() },
    ...(existing ? [{ label: existing.name, to: show.url(existing.id) }] : []),
    { label: title.value },
]);

/** The item open in the panel, by key, so it follows the item when it's dragged. */
const editingKey = ref<number | null>(null);

const editing = computed(() => {
    for (const [sectionIndex, section] of form.sections.entries()) {
        const itemIndex = section.items.findIndex(
            (item) => item._key === editingKey.value,
        );

        if (itemIndex !== -1) {
            return {
                item: section.items[itemIndex],
                prefix: `sections.${sectionIndex}.items.${itemIndex}`,
            };
        }
    }

    return null;
});

const panelOpen = computed({
    get: () => editing.value !== null,
    set: (open: boolean) => {
        if (!open) {
            editingKey.value = null;
        }
    },
});

function addSection() {
    form.sections.push({
        id: null,
        name: '',
        description: null,
        items: [],
        _key: nextKey++,
    });
}

function removeSection(sectionIndex: number) {
    form.sections.splice(sectionIndex, 1);
}

function addItem(section: EditorSection) {
    const item = editorItem({
        id: null,
        name: '',
        description: null,
        service_id: null,
        image_file_id: null,
        price: null,
        is_available: true,
        tracks_stock: false,
        stock: null,
        tag_ids: [],
        variations: [],
    });

    section.items.push(item);
    editingKey.value = item._key;
}

function removeItem(section: EditorSection, itemIndex: number) {
    section.items.splice(itemIndex, 1);
}

/** Whether anything under the prefix failed validation, to flag it in the list. */
function hasErrors(prefix: string): boolean {
    return Object.keys(errors.value).some(
        (key) => key === prefix || key.startsWith(`${prefix}.`),
    );
}

/** The price an item shows, worked out as the server would. */
function itemPrice(item: EditorItem): string {
    if (item.service_id !== null) {
        const service = props.services?.find(
            (option) => option.value === item.service_id,
        );

        return catalogueItemPrice(
            { amount: service?.price ?? null, from: service?.from ?? false },
            props.currency,
        );
    }

    if (item.variations.length) {
        const prices = item.variations.map((variation) =>
            Math.round(Number(variation.price || 0) * 100),
        );

        return catalogueItemPrice(
            {
                amount: Math.min(...prices),
                from: new Set(prices).size > 1,
            },
            props.currency,
        );
    }

    return catalogueItemPrice(
        {
            amount:
                item.price === null || item.price === ''
                    ? null
                    : Math.round(Number(item.price) * 100),
            from: false,
        },
        props.currency,
    );
}

function submit() {
    form.transform((data) => ({
        ...data,
        slug: data.slug || null,
        description: data.description || null,
        available_from: data.available_from || null,
        available_until: data.available_until || null,
        starts_on: data.starts_on || null,
        ends_on: data.ends_on || null,
        sections: data.sections.map((section) => ({
            id: section.id,
            name: section.name,
            description: section.description || null,
            items: section.items.map((item) => ({
                id: item.id,
                name: item.name,
                description: item.description || null,
                service_id: item.service_id,
                image_file_id: item.image_file_id,
                price:
                    item.price === null || item.price === ''
                        ? null
                        : item.price,
                is_available: item.is_available,
                tracks_stock: item.tracks_stock,
                stock: item.stock,
                tag_ids: item.tag_ids,
                variations: item.variations.map((variation) => ({
                    id: variation.id,
                    name: variation.name,
                    price: variation.price,
                    stock: variation.stock,
                })),
            })),
        })),
    }));

    if (existing) {
        form.submit(update(existing.id));
    } else {
        form.submit(store());
    }
}
</script>

<template>
    <EditorLayout
        :title="title"
        :breadcrumbs="breadcrumbs"
        :is-dirty="form.isDirty"
        :processing="form.processing"
        :submit-label="
            existing ? undefined : trans('catalogue.catalogues.create.submit')
        "
        @submit="submit"
    >
        <UPageCard
            variant="subtle"
            :title="trans('catalogue.catalogues.cards.contents')"
            :description="trans('catalogue.catalogues.cards.contents_help')"
        >
            <p v-if="!form.sections.length" class="text-muted text-sm">
                {{ trans('catalogue.sections.empty') }}
            </p>

            <VueDraggable
                v-model="form.sections"
                handle=".section-handle"
                :animation="150"
                class="flex flex-col gap-4"
            >
                <section
                    v-for="(section, sectionIndex) in form.sections"
                    :key="section._key"
                    class="border-default bg-default flex flex-col gap-3 rounded-lg border p-3"
                >
                    <div class="flex items-start gap-2">
                        <UButton
                            class="section-handle mt-1 cursor-grab"
                            color="neutral"
                            variant="link"
                            size="sm"
                            icon="i-lucide-grip-vertical"
                            :aria-label="trans('catalogue.sections.drag')"
                        />
                        <div class="flex min-w-0 flex-1 flex-col gap-2">
                            <UFormField
                                :error="errors[`sections.${sectionIndex}.name`]"
                            >
                                <UInput
                                    v-model="section.name"
                                    :placeholder="
                                        trans('catalogue.sections.name')
                                    "
                                    :aria-label="
                                        trans('catalogue.sections.name')
                                    "
                                    class="w-full font-medium"
                                />
                            </UFormField>
                            <UFormField
                                :error="
                                    errors[
                                        `sections.${sectionIndex}.description`
                                    ]
                                "
                            >
                                <UInput
                                    v-model="section.description as string"
                                    :placeholder="
                                        trans('catalogue.sections.description')
                                    "
                                    :aria-label="
                                        trans('catalogue.sections.description')
                                    "
                                    size="sm"
                                    variant="soft"
                                    class="w-full"
                                />
                            </UFormField>
                        </div>
                        <UButton
                            color="neutral"
                            variant="ghost"
                            icon="i-lucide-trash-2"
                            :aria-label="trans('catalogue.sections.remove')"
                            @click="removeSection(sectionIndex)"
                        />
                    </div>

                    <p
                        v-if="!section.items.length"
                        class="text-muted pl-9 text-sm"
                    >
                        {{ trans('catalogue.sections.no_items') }}
                    </p>

                    <!-- Items can be dropped into another section, including an empty one -->
                    <VueDraggable
                        v-model="section.items"
                        group="catalogue-items"
                        handle=".item-handle"
                        :animation="150"
                        class="flex min-h-4 flex-col gap-1"
                    >
                        <div
                            v-for="(item, itemIndex) in section.items"
                            :key="item._key"
                            class="hover:bg-elevated flex items-center gap-2 rounded-md py-1 pr-1"
                            :class="{
                                'ring-error ring-1': hasErrors(
                                    `sections.${sectionIndex}.items.${itemIndex}`,
                                ),
                            }"
                        >
                            <UButton
                                class="item-handle cursor-grab"
                                color="neutral"
                                variant="link"
                                size="sm"
                                icon="i-lucide-grip-vertical"
                                :aria-label="trans('catalogue.sections.drag')"
                            />
                            <button
                                type="button"
                                class="focus-visible:outline-primary flex min-w-0 flex-1 flex-col gap-1 rounded-md text-left focus-visible:outline-2"
                                @click="editingKey = item._key"
                            >
                                <span class="flex items-center gap-2">
                                    <span class="truncate text-sm font-medium">
                                        {{
                                            item.name ||
                                            trans('catalogue.items.new')
                                        }}
                                    </span>
                                    <UBadge
                                        v-if="!item.is_available"
                                        color="neutral"
                                        variant="subtle"
                                        size="sm"
                                        :label="
                                            trans('catalogue.items.sold_out')
                                        "
                                    />
                                    <span class="text-muted ml-auto text-sm">
                                        {{ itemPrice(item) }}
                                    </span>
                                </span>
                                <CatalogueTagList
                                    :tag-ids="item.tag_ids"
                                    :tags="tags"
                                />
                            </button>
                            <UButton
                                color="neutral"
                                variant="ghost"
                                size="sm"
                                icon="i-lucide-trash-2"
                                :aria-label="trans('catalogue.items.remove')"
                                @click="removeItem(section, itemIndex)"
                            />
                        </div>
                    </VueDraggable>

                    <UButton
                        class="self-start"
                        color="neutral"
                        variant="subtle"
                        size="sm"
                        icon="i-lucide-plus"
                        :label="trans('catalogue.items.add')"
                        @click="addItem(section)"
                    />
                </section>
            </VueDraggable>

            <UButton
                class="self-start"
                color="neutral"
                variant="subtle"
                icon="i-lucide-plus"
                :label="trans('catalogue.sections.add')"
                @click="addSection"
            />
        </UPageCard>

        <template #sidebar>
            <UPageCard
                variant="subtle"
                :title="trans('catalogue.catalogues.cards.details')"
            >
                <UFormField
                    :label="trans('catalogue.catalogues.fields.name')"
                    :error="form.errors.name"
                    required
                >
                    <UInput v-model="form.name" class="w-full" />
                </UFormField>

                <UFormField
                    :label="trans('catalogue.catalogues.fields.description')"
                    :help="
                        trans('catalogue.catalogues.fields.description_help')
                    "
                    :error="form.errors.description"
                >
                    <UTextarea
                        v-model="form.description"
                        :rows="2"
                        autoresize
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    :label="trans('catalogue.catalogues.fields.slug')"
                    :help="trans('catalogue.catalogues.fields.slug_help')"
                    :error="form.errors.slug"
                >
                    <UInput v-model="form.slug" class="w-full" />
                </UFormField>

                <USwitch
                    v-model="form.is_published"
                    :label="trans('catalogue.catalogues.fields.is_published')"
                    :description="
                        trans('catalogue.catalogues.fields.is_published_help')
                    "
                />
            </UPageCard>

            <UPageCard
                variant="subtle"
                :title="trans('catalogue.catalogues.cards.schedule')"
                :description="trans('catalogue.catalogues.cards.schedule_help')"
            >
                <UFormField
                    :label="trans('catalogue.catalogues.fields.available_days')"
                    :help="
                        trans('catalogue.catalogues.fields.available_days_help')
                    "
                    :error="form.errors.available_days"
                >
                    <UCheckboxGroup
                        v-model="form.available_days"
                        :items="dayItems"
                        orientation="horizontal"
                        class="flex-wrap"
                    />
                </UFormField>

                <div class="grid grid-cols-2 gap-3">
                    <UFormField
                        :label="
                            trans('catalogue.catalogues.fields.available_from')
                        "
                        :error="form.errors.available_from"
                    >
                        <UInput
                            v-model="form.available_from"
                            type="time"
                            class="w-full"
                        />
                    </UFormField>
                    <UFormField
                        :label="
                            trans('catalogue.catalogues.fields.available_until')
                        "
                        :error="form.errors.available_until"
                    >
                        <UInput
                            v-model="form.available_until"
                            type="time"
                            class="w-full"
                        />
                    </UFormField>
                </div>
                <p class="text-muted -mt-2 text-xs">
                    {{ trans('catalogue.catalogues.fields.times_help') }}
                </p>

                <div class="grid grid-cols-2 gap-3">
                    <UFormField
                        :label="trans('catalogue.catalogues.fields.starts_on')"
                        :error="form.errors.starts_on"
                    >
                        <UInput
                            v-model="form.starts_on"
                            type="date"
                            class="w-full"
                        />
                    </UFormField>
                    <UFormField
                        :label="trans('catalogue.catalogues.fields.ends_on')"
                        :error="form.errors.ends_on"
                    >
                        <UInput
                            v-model="form.ends_on"
                            type="date"
                            class="w-full"
                        />
                    </UFormField>
                </div>
                <p class="text-muted -mt-2 text-xs">
                    {{ trans('catalogue.catalogues.fields.dates_help') }}
                </p>
            </UPageCard>
        </template>

        <USlideover
            v-model:open="panelOpen"
            :title="
                editing?.item.id
                    ? trans('catalogue.items.edit')
                    : trans('catalogue.items.new')
            "
        >
            <template #body>
                <CatalogueItemFields
                    v-if="editing"
                    :model-value="editing.item"
                    :tags="tags"
                    :services="services"
                    :currency="currency"
                    :error-prefix="editing.prefix"
                    :errors="errors"
                />
            </template>
            <template #footer>
                <UButton
                    :label="trans('catalogue.items.done')"
                    block
                    @click="panelOpen = false"
                />
            </template>
        </USlideover>
    </EditorLayout>
</template>
