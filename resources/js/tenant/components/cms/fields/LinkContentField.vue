<script setup lang="ts">
import type { LinkValue, TemplateFieldData } from '@/types';
import { useLinkPages } from '../nestedTemplates';

/**
 * A page, which the link follows if its URL changes, or a URL. Nothing is stored until it goes
 * somewhere, so choosing a type alone doesn't make an optional component look filled in.
 */
const props = defineProps<{
    field: TemplateFieldData;
    disabled?: boolean;
}>();

const value = defineModel<LinkValue | null>({ default: null });

const { trans } = useTrans();

const pages = useLinkPages();

const linkType = ref<LinkValue['link_type']>(
    value.value?.link_type ?? (pages.value.length ? 'page' : 'url'),
);
const pageId = ref(value.value?.page_id ?? null);
const url = ref(value.value?.url ?? '');
const opensInNewTab = ref(value.value?.target === '_blank');

// E.g. a saved component's values being copied in. What the other type had is kept, so switching
// between them doesn't lose it.
watch(value, (link) => {
    if (link) {
        linkType.value = link.link_type;
        pageId.value = link.page_id ?? pageId.value;
        url.value = link.url ?? url.value;
        opensInNewTab.value = link.target === '_blank';
    }
});

function store() {
    const isPage = linkType.value === 'page';
    const goesSomewhere = isPage ? pageId.value !== null : url.value !== '';

    value.value = goesSomewhere
        ? {
              link_type: linkType.value,
              page_id: isPage ? pageId.value : null,
              url: isPage ? null : url.value,
              target: opensInNewTab.value ? '_blank' : null,
          }
        : null;
}

const typeModel = computed({
    get: () => linkType.value,
    set: (type: LinkValue['link_type']) => {
        linkType.value = type;
        store();
    },
});

/** It links to a page that's since been deleted. */
const isPageGone = computed(
    () =>
        linkType.value === 'page' &&
        pageId.value !== null &&
        !pages.value.some((page) => page.value === pageId.value),
);

const pageModel = computed({
    get: () => (isPageGone.value ? undefined : (pageId.value ?? undefined)),
    set: (id: number | undefined) => {
        pageId.value = id ?? null;
        store();
    },
});

const urlModel = computed({
    get: () => url.value,
    set: (typed: string) => {
        url.value = typed;
        store();
    },
});

const targetModel = computed({
    get: () => opensInNewTab.value,
    set: (opens: boolean) => {
        opensInNewTab.value = opens;
        store();
    },
});

const typeItems = computed(() => [
    { label: trans('cms.content.link.types.page'), value: 'page' },
    { label: trans('cms.content.link.types.url'), value: 'url' },
]);

const canRemove = computed(
    () => !props.disabled && !props.field.is_required && value.value !== null,
);

function remove() {
    pageId.value = null;
    url.value = '';
    opensInNewTab.value = false;
    store();
}
</script>

<template>
    <div class="flex flex-col gap-3">
        <URadioGroup
            v-model="typeModel"
            :items="typeItems"
            :disabled="disabled"
            :aria-label="trans('cms.content.link.type')"
            orientation="horizontal"
        />

        <USelectMenu
            v-if="linkType === 'page'"
            v-model="pageModel"
            :items="pages"
            value-key="value"
            :disabled="disabled"
            :placeholder="trans('cms.content.link.page_placeholder')"
            class="w-full"
        />
        <UInput
            v-else
            v-model="urlModel"
            :disabled="disabled"
            placeholder="https://"
            class="w-full font-mono"
        />

        <UAlert
            v-if="isPageGone && !disabled"
            color="warning"
            variant="subtle"
            icon="i-lucide-triangle-alert"
            :description="trans('cms.content.link.page_gone')"
        />
        <p v-else-if="!disabled" class="text-muted text-sm">
            {{
                linkType === 'page'
                    ? trans('cms.content.link.page_help')
                    : trans('cms.content.link.url_help')
            }}
        </p>

        <div class="flex flex-wrap items-center justify-between gap-2">
            <USwitch
                v-model="targetModel"
                :disabled="disabled"
                :label="trans('cms.content.link.target')"
            />
            <UButton
                v-if="canRemove"
                color="neutral"
                variant="ghost"
                size="sm"
                icon="i-lucide-unlink"
                :label="trans('cms.content.link.remove')"
                @click="remove"
            />
        </div>
    </div>
</template>
