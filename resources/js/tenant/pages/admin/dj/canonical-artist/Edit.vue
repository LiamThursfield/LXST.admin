<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import {
    index,
    show,
    update,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/CanonicalArtistController';

const props = defineProps<{
    artist: {
        id: number;
        name: string;
        is_verified: boolean;
    };
}>();

const { trans } = useTrans();

const form = useForm({ name: props.artist.name });

function save() {
    form.submit(update(props.artist.id));
}

const breadcrumbs = computed(() => [
    { label: trans('dj.canonical_artists.title'), to: index.url() },
    { label: props.artist.name, to: show.url(props.artist.id) },
    { label: trans('common.actions.edit') },
]);
</script>

<template>
    <EditorLayout
        :title="trans('dj.canonical_artists.edit.title', { name: artist.name })"
        :breadcrumbs="breadcrumbs"
        :is-dirty="form.isDirty"
        :processing="form.processing"
        narrow
        @submit="save"
    >
        <template v-if="artist.is_verified" #badges>
            <UBadge color="success" variant="subtle">
                {{ trans('dj.matching.verified') }}
            </UBadge>
        </template>

        <UPageCard
            :description="trans('dj.canonical_artists.show.rename_help')"
            variant="subtle"
        >
            <UFormField
                :label="trans('dj.canonical_artists.fields.name')"
                :error="form.errors.name"
                required
            >
                <UInput v-model="form.name" class="w-full" />
            </UFormField>
        </UPageCard>
    </EditorLayout>
</template>
