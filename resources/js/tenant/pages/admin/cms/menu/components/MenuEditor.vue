<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import {
    index,
    store,
    update,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Cms/MenuController';
import type { EditorTab } from '@/components/EditorLayout.vue';
import BuilderFieldPanel from '@/tenant/components/builder/BuilderFieldPanel.vue';
import type { MenuData, MenuItem, MenuPageOption } from '@/types';
import MenuItemTree from './MenuItemTree.vue';

/** How many levels deep items can go (see backend `MenuItemData::MAX_DEPTH`). */
const MAX_DEPTH = 3;

/**
 * Creates, edits or shows a menu. The Items tab has the item tree beside the chosen item's
 * settings; the Details tab has the menu's name and slug.
 */
const props = withDefaults(
    defineProps<{
        menu?: MenuData;
        pages: MenuPageOption[];
        readonly?: boolean;
    }>(),
    {
        menu: undefined,
        readonly: false,
    },
);

const { trans } = useTrans();

let nextKey = 0;

/** Copies the items, giving each a key so it keeps its identity while dragged. */
function withKeys(items: MenuItem[]): MenuItem[] {
    return items.map((item) => ({
        ...item,
        _key: nextKey++,
        children: withKeys(item.children ?? []),
    }));
}

const menu = props.menu;

const form = useForm({
    name: menu?.name ?? '',
    slug: menu?.slug ?? '',
    menu_items: withKeys(menu?.menu_items ?? []),
});

/**
 * Server validation errors, including nested ones such as `menu_items.0.children.1.label`. Read
 * through `unknown`: Inertia can't build error keys for the recursive `children`.
 */
const errors = computed(
    () =>
        (form as unknown as { errors: Record<string, string | undefined> })
            .errors,
);

/** Where an item is in the tree: the list it's in, its index there, its depth and its error path. */
type ItemPlace = {
    item: MenuItem;
    list: MenuItem[];
    index: number;
    depth: number;
    path: string;
};

/** Every item, in tree order (each item before its children). */
function places(list: MenuItem[], path = 'menu_items', depth = 1): ItemPlace[] {
    return list.flatMap((item, itemIndex) => [
        { item, list, index: itemIndex, depth, path: `${path}.${itemIndex}` },
        ...places(item.children, `${path}.${itemIndex}.children`, depth + 1),
    ]);
}

const itemCount = computed(() => places(form.menu_items).length);

/** The chosen item's `_key`. */
const selectedKey = ref<number | null>(null);

const selected = computed(
    () =>
        places(form.menu_items).find(
            (place) => place.item._key === selectedKey.value,
        ) ?? null,
);

const isPanelOpen = computed({
    get: () => selected.value !== null,
    set: (open: boolean) => {
        if (!open) {
            selectedKey.value = null;
        }
    },
});

function newItem(): MenuItem {
    return {
        _key: nextKey++,
        label: trans('cms.menus.items.new'),
        link_type: props.pages.length ? 'page' : 'url',
        page_id: null,
        url: null,
        target: null,
        rel: null,
        custom_class: null,
        children: [],
    };
}

/** Adds an item after the chosen one (at its level), or at the end, and chooses it. */
function addItem() {
    const item = newItem();
    const place = selected.value;

    if (place) {
        place.list.splice(place.index + 1, 0, item);
    } else {
        form.menu_items.push(item);
    }

    selectedKey.value = item._key ?? null;
}

function addChild() {
    const place = selected.value;

    if (!place || place.depth >= MAX_DEPTH) {
        return;
    }

    const item = newItem();
    place.item.children.push(item);
    selectedKey.value = item._key ?? null;
}

/** Removes the chosen item (and its children), choosing the next one at its level. */
function removeItem() {
    const place = selected.value;

    if (!place) {
        return;
    }

    place.list.splice(place.index, 1);

    const next = place.list[place.index] ?? place.list[place.index - 1];
    selectedKey.value = next?._key ?? null;
}

function itemError(key: string): string | undefined {
    return selected.value
        ? errors.value[`${selected.value.path}.${key}`]
        : undefined;
}

/** Item errors that aren't on a field in the panel, e.g. too many levels. */
const listErrors = computed(() =>
    Object.entries(errors.value)
        .filter(
            ([key]) =>
                key.startsWith('menu_items') &&
                !/\.(label|link_type|page_id|url|target|rel|custom_class)$/.test(
                    key,
                ),
        )
        .map(([, message]) => message),
);

const linkTypeItems = computed(() => [
    { value: 'page', label: trans('cms.menus.item.link_types.page') },
    { value: 'url', label: trans('cms.menus.item.link_types.url') },
]);

const itemErrorCount = computed(
    () =>
        Object.entries(errors.value).filter(
            ([key, message]) => message && key.startsWith('menu_items'),
        ).length,
);

const tab = ref('items');

const tabs = computed<EditorTab[]>(() => [
    {
        value: 'items',
        label: trans('cms.menus.sections.items'),
        count: itemCount.value,
        errorCount: itemErrorCount.value,
    },
    {
        value: 'details',
        label: trans('cms.menus.sections.details'),
        errorCount: ['name', 'slug'].filter((key) => errors.value[key]).length,
    },
]);

/** After a failed save, choose the first item with errors, or else show the details. */
function revealFirstError() {
    const place = places(form.menu_items).find((candidate) =>
        Object.keys(errors.value).some(
            (key) =>
                key.startsWith(`${candidate.path}.`) &&
                !key.startsWith(`${candidate.path}.children.`),
        ),
    );

    if (place) {
        selectedKey.value = place.item._key ?? null;
    }

    tab.value = itemErrorCount.value ? 'items' : 'details';
}

function onSubmit() {
    if (props.readonly) return;

    form.submit(menu ? update({ menu: menu.id }) : store(), {
        onError: revealFirstError,
    });
}

const title = computed(() => {
    if (!menu) {
        return trans('cms.menus.create.title');
    }

    return props.readonly
        ? trans('cms.menus.show.title', { name: menu.name })
        : trans('cms.menus.edit.title', { name: menu.name });
});

const breadcrumbs = computed(() => [
    { label: trans('cms.menus.index.title'), to: index.url() },
    { label: menu?.name ?? trans('cms.menus.create.title') },
]);
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
        :submit-label="menu ? undefined : trans('cms.menus.create.submit')"
        @submit="onSubmit"
    >
        <div
            v-show="tab === 'items'"
            class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,26rem)_minmax(0,1fr)] lg:items-start xl:grid-cols-[minmax(0,30rem)_minmax(0,1fr)]"
        >
            <section class="bg-elevated/50 ring-default rounded-lg ring">
                <div
                    class="border-default flex items-center gap-2 border-b py-3 pl-4 pr-3"
                >
                    <div class="min-w-0 flex-1">
                        <h2 class="text-highlighted font-semibold">
                            {{ trans('cms.menus.sections.items') }}
                        </h2>
                        <p v-if="!readonly" class="text-muted text-sm">
                            {{
                                trans('cms.menus.items.intro', {
                                    depth: MAX_DEPTH,
                                })
                            }}
                        </p>
                    </div>
                    <UButton
                        v-if="!readonly"
                        size="sm"
                        icon="i-lucide-plus"
                        :label="trans('cms.menus.items.add')"
                        @click="addItem"
                    />
                </div>

                <div class="flex flex-col gap-2 p-2">
                    <p
                        v-for="message in listErrors"
                        :key="message"
                        class="text-error px-2 text-sm"
                    >
                        {{ message }}
                    </p>

                    <p
                        v-if="!form.menu_items.length"
                        class="text-muted p-4 text-center text-sm"
                    >
                        {{ trans('cms.menus.items.empty') }}
                    </p>

                    <MenuItemTree
                        v-model="form.menu_items"
                        v-model:selected="selectedKey"
                        path="menu_items"
                        :depth="1"
                        :max-depth="MAX_DEPTH"
                        :pages="pages"
                        :errors="errors"
                        :readonly="readonly"
                    />
                </div>
            </section>

            <BuilderFieldPanel
                v-model:open="isPanelOpen"
                :title="selected?.item.label || trans('cms.menus.item.title')"
                :description="trans('cms.menus.item.title')"
                :empty-text="trans('cms.menus.items.choose')"
            >
                <template v-if="!readonly && selected" #actions>
                    <UButton
                        v-if="selected.depth < MAX_DEPTH"
                        color="neutral"
                        variant="ghost"
                        icon="i-lucide-corner-down-right"
                        :aria-label="trans('cms.menus.items.add_child')"
                        @click="addChild"
                    />
                    <UButton
                        color="error"
                        variant="ghost"
                        icon="i-lucide-trash"
                        :aria-label="trans('cms.menus.items.remove')"
                        @click="removeItem"
                    />
                </template>

                <div
                    v-if="selected"
                    :key="selected.item._key"
                    class="flex flex-col gap-4"
                >
                    <UFormField
                        :label="trans('cms.menus.item.label')"
                        :error="itemError('label')"
                        required
                    >
                        <UInput
                            v-model="selected.item.label"
                            :disabled="readonly"
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        :label="trans('cms.menus.item.link_type')"
                        :error="itemError('link_type')"
                    >
                        <URadioGroup
                            v-model="selected.item.link_type"
                            :items="linkTypeItems"
                            :disabled="readonly"
                            orientation="horizontal"
                        />
                    </UFormField>

                    <UFormField
                        v-if="selected.item.link_type === 'page'"
                        :label="trans('cms.menus.item.page')"
                        :help="
                            readonly
                                ? undefined
                                : trans('cms.menus.item.page_help')
                        "
                        :error="itemError('page_id')"
                        required
                    >
                        <USelectMenu
                            v-model.nullable="selected.item.page_id"
                            :items="pages"
                            value-key="value"
                            :disabled="readonly"
                            :placeholder="
                                trans('cms.menus.item.page_placeholder')
                            "
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        v-else
                        :label="trans('cms.menus.item.url')"
                        :help="
                            readonly
                                ? undefined
                                : trans('cms.menus.item.url_help')
                        "
                        :error="itemError('url')"
                        required
                    >
                        <UInput
                            v-model.nullable="selected.item.url"
                            :disabled="readonly"
                            placeholder="https://"
                            class="w-full font-mono"
                        />
                    </UFormField>

                    <UFormField :error="itemError('target')">
                        <USwitch
                            :model-value="selected.item.target === '_blank'"
                            :disabled="readonly"
                            :label="trans('cms.menus.item.target')"
                            @update:model-value="
                                (open) =>
                                    selected &&
                                    (selected.item.target = open
                                        ? '_blank'
                                        : null)
                            "
                        />
                    </UFormField>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <UFormField
                            :label="trans('cms.menus.item.rel')"
                            :help="
                                readonly
                                    ? undefined
                                    : trans('cms.menus.item.rel_help')
                            "
                            :error="itemError('rel')"
                        >
                            <UInput
                                v-model.nullable="selected.item.rel"
                                :disabled="readonly"
                                class="w-full"
                            />
                        </UFormField>

                        <UFormField
                            :label="trans('cms.menus.item.custom_class')"
                            :error="itemError('custom_class')"
                        >
                            <UInput
                                v-model.nullable="selected.item.custom_class"
                                :disabled="readonly"
                                class="w-full font-mono"
                            />
                        </UFormField>
                    </div>
                </div>
            </BuilderFieldPanel>
        </div>

        <UPageCard
            v-show="tab === 'details'"
            variant="subtle"
            :title="trans('cms.menus.sections.details')"
        >
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <UFormField
                    :label="trans('cms.menus.fields.name')"
                    :error="errors.name"
                    required
                >
                    <UInput
                        v-model="form.name"
                        :disabled="readonly"
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    :label="trans('cms.menus.fields.slug')"
                    :help="
                        readonly
                            ? undefined
                            : trans('cms.menus.fields.slug_help')
                    "
                    :error="errors.slug"
                >
                    <UInput
                        v-model="form.slug"
                        :disabled="readonly"
                        class="w-full font-mono"
                    />
                </UFormField>
            </div>
        </UPageCard>
    </EditorLayout>
</template>
