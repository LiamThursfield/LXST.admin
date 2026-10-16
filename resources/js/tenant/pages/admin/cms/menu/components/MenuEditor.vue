<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import {
    store,
    update,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Cms/MenuController';
import type { MenuData, MenuItem, MenuPageOption } from '@/types';
import MenuItemTree from './MenuItemTree.vue';

/** How many levels deep items can go (see backend `MenuItemData::MAX_DEPTH`). */
const MAX_DEPTH = 3;

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

/** Server validation errors, including nested ones such as `menu_items.0.children.1.label`. */
const errors = computed(
    () => form.errors as Record<string, string | undefined>,
);

/** The item being edited in the dialog, and where its errors are. */
const editing = ref<{ item: MenuItem; path: string } | null>(null);
const editOpen = computed({
    get: () => editing.value !== null,
    set: (open: boolean) => {
        if (!open) editing.value = null;
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

function addItem() {
    const item = newItem();
    form.menu_items.push(item);
    editing.value = { item, path: `menu_items.${form.menu_items.length - 1}` };
}

function addChild(parent: MenuItem, parentPath: string) {
    const item = newItem();
    parent.children.push(item);
    editing.value = {
        item,
        path: `${parentPath}.children.${parent.children.length - 1}`,
    };
}

function itemError(key: string): string | undefined {
    return editing.value
        ? errors.value[`${editing.value.path}.${key}`]
        : undefined;
}

/** Item errors that aren't on a field in the dialog, e.g. too many levels. */
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

function onSubmit() {
    if (props.readonly) return;

    form.submit(menu ? update({ menu: menu.id }) : store());
}
</script>

<template>
    <form class="space-y-6" @submit.prevent="onSubmit">
        <UPageCard
            variant="subtle"
            :title="trans('cms.menus.sections.details')"
        >
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <UFormField
                    :label="trans('cms.menus.fields.name')"
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
                    :label="trans('cms.menus.fields.slug')"
                    :help="
                        readonly
                            ? undefined
                            : trans('cms.menus.fields.slug_help')
                    "
                    :error="form.errors.slug"
                >
                    <UInput
                        v-model="form.slug"
                        :disabled="readonly"
                        class="w-full font-mono"
                    />
                </UFormField>
            </div>
        </UPageCard>

        <UPageCard
            variant="subtle"
            :title="trans('cms.menus.sections.items')"
            :description="
                readonly
                    ? undefined
                    : trans('cms.menus.items.intro', { depth: MAX_DEPTH })
            "
        >
            <div class="flex flex-col gap-3">
                <p
                    v-for="message in listErrors"
                    :key="message"
                    class="text-error text-sm"
                >
                    {{ message }}
                </p>

                <p v-if="!form.menu_items.length" class="text-muted text-sm">
                    {{ trans('cms.menus.items.empty') }}
                </p>

                <MenuItemTree
                    v-model="form.menu_items"
                    path="menu_items"
                    :depth="1"
                    :max-depth="MAX_DEPTH"
                    :pages="pages"
                    :errors="errors"
                    :readonly="readonly"
                    @edit="(item, path) => (editing = { item, path })"
                    @add-child="addChild"
                />

                <UButton
                    v-if="!readonly"
                    class="w-fit"
                    color="neutral"
                    variant="subtle"
                    icon="i-lucide-plus"
                    :label="trans('cms.menus.items.add')"
                    @click="addItem"
                />
            </div>
        </UPageCard>

        <div v-if="!readonly" class="flex justify-end gap-2">
            <UButton
                type="submit"
                color="primary"
                :loading="form.processing"
                :label="
                    menu
                        ? trans('cms.menus.edit.submit')
                        : trans('cms.menus.create.submit')
                "
            />
        </div>

        <UModal v-model:open="editOpen" :title="trans('cms.menus.item.title')">
            <template #body>
                <div v-if="editing" class="flex flex-col gap-4">
                    <UFormField
                        :label="trans('cms.menus.item.label')"
                        :error="itemError('label')"
                        required
                    >
                        <UInput
                            v-model="editing.item.label"
                            autofocus
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        :label="trans('cms.menus.item.link_type')"
                        :error="itemError('link_type')"
                    >
                        <URadioGroup
                            v-model="editing.item.link_type"
                            :items="linkTypeItems"
                            orientation="horizontal"
                        />
                    </UFormField>

                    <UFormField
                        v-if="editing.item.link_type === 'page'"
                        :label="trans('cms.menus.item.page')"
                        :help="trans('cms.menus.item.page_help')"
                        :error="itemError('page_id')"
                        required
                    >
                        <USelectMenu
                            v-model="editing.item.page_id"
                            :items="pages"
                            value-key="value"
                            :placeholder="
                                trans('cms.menus.item.page_placeholder')
                            "
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        v-else
                        :label="trans('cms.menus.item.url')"
                        :help="trans('cms.menus.item.url_help')"
                        :error="itemError('url')"
                        required
                    >
                        <UInput
                            v-model="editing.item.url"
                            placeholder="https://"
                            class="w-full font-mono"
                        />
                    </UFormField>

                    <UFormField :error="itemError('target')">
                        <USwitch
                            :model-value="editing.item.target === '_blank'"
                            :label="trans('cms.menus.item.target')"
                            @update:model-value="
                                (open) =>
                                    editing &&
                                    (editing.item.target = open
                                        ? '_blank'
                                        : null)
                            "
                        />
                    </UFormField>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <UFormField
                            :label="trans('cms.menus.item.rel')"
                            :help="trans('cms.menus.item.rel_help')"
                            :error="itemError('rel')"
                        >
                            <UInput v-model="editing.item.rel" class="w-full" />
                        </UFormField>

                        <UFormField
                            :label="trans('cms.menus.item.custom_class')"
                            :error="itemError('custom_class')"
                        >
                            <UInput
                                v-model="editing.item.custom_class"
                                class="w-full font-mono"
                            />
                        </UFormField>
                    </div>

                    <div class="flex justify-end">
                        <UButton
                            :label="trans('cms.menus.item.done')"
                            @click="editing = null"
                        />
                    </div>
                </div>
            </template>
        </UModal>
    </form>
</template>
