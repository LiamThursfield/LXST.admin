<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    index,
    store,
    update,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Bookings/ServiceCategoryController';
import type { ServiceCategoryData } from '@/types';

const props = defineProps<{
    category?: ServiceCategoryData;
}>();

const { trans } = useTrans();

const form = useForm({
    name: props.category?.name ?? '',
    order: props.category?.order ?? 0,
});

const title = computed(() =>
    trans(
        props.category
            ? 'bookings.categories.edit.title'
            : 'bookings.categories.create.title',
    ),
);

const breadcrumbs = computed(() => [
    { label: trans('bookings.categories.index.title'), to: index.url() },
    { label: props.category?.name ?? title.value },
]);

function submit() {
    if (props.category) {
        form.submit(update(props.category.id));
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
            category ? undefined : trans('bookings.categories.create.submit')
        "
        narrow
        @submit="submit"
    >
        <UPageCard variant="subtle">
            <UFormField
                :label="trans('bookings.categories.fields.name')"
                name="name"
                :error="form.errors.name"
                required
            >
                <UInput v-model="form.name" class="w-full" />
            </UFormField>

            <UFormField
                :label="trans('bookings.categories.fields.order')"
                :help="trans('bookings.categories.fields.order_help')"
                name="order"
                :error="form.errors.order"
            >
                <UInput
                    v-model.number="form.order"
                    type="number"
                    min="0"
                    class="w-full"
                />
            </UFormField>
        </UPageCard>
    </EditorLayout>
</template>
