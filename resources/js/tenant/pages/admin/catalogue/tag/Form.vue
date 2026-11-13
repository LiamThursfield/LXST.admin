<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import {
    index,
    store,
    update,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Catalogue/TagController';
import type { CatalogueTagData } from '@/types';

const props = defineProps<{
    tag?: { data: CatalogueTagData };
    kinds: Array<{ value: string; label: string }>;
    /** The icons a tag can have, e.g. "i-lucide-leaf". */
    icons: string[];
}>();

const { trans } = useTrans();

const existing = props.tag?.data;

const form = useForm({
    name: existing?.name ?? '',
    kind: existing?.kind ?? 'other',
    icon: existing?.icon ?? null,
    order: existing?.order ?? 0,
});

const title = computed(() =>
    trans(
        existing ? 'catalogue.tags.edit.title' : 'catalogue.tags.create.title',
    ),
);

const breadcrumbs = computed(() => [
    { label: trans('catalogue.tags.index.title'), to: index.url() },
    { label: existing?.name ?? title.value },
]);

const iconItems = computed(() => [
    { value: null, label: trans('catalogue.tags.fields.no_icon') },
    ...props.icons.map((icon) => ({
        value: icon,
        label: icon.replace('i-lucide-', '').replaceAll('-', ' '),
        icon,
    })),
]);

function submit() {
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
            existing ? undefined : trans('catalogue.tags.create.submit')
        "
        narrow
        @submit="submit"
    >
        <UPageCard variant="subtle">
            <UFormField
                :label="trans('catalogue.tags.fields.name')"
                :error="form.errors.name"
                required
            >
                <UInput v-model="form.name" class="w-full" />
            </UFormField>

            <UFormField
                :label="trans('catalogue.tags.fields.kind')"
                :help="trans('catalogue.tags.fields.kind_help')"
                :error="form.errors.kind"
                required
            >
                <USelect
                    v-model="form.kind"
                    :items="kinds"
                    value-key="value"
                    class="w-full"
                />
            </UFormField>

            <UFormField
                :label="trans('catalogue.tags.fields.icon')"
                :error="form.errors.icon"
            >
                <USelectMenu
                    v-model="form.icon"
                    :items="iconItems"
                    value-key="value"
                    :icon="form.icon ?? undefined"
                    class="w-full"
                />
            </UFormField>
        </UPageCard>
    </EditorLayout>
</template>
