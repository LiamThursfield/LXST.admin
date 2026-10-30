<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import {
    index,
    store,
    update,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Cms/RedirectController';
import type { RedirectData } from '@/types';

const props = defineProps<{
    redirect?: RedirectData;
}>();

const { trans } = useTrans();

const form = useForm({
    url_from: props.redirect?.url_from ?? '',
    url_to: props.redirect?.url_to ?? '',
    is_enabled: props.redirect?.is_enabled ?? true,
    is_permanent: props.redirect?.is_permanent ?? true,
    published_at: props.redirect?.published_at_local ?? '',
    expired_at: props.redirect?.expired_at_local ?? '',
});

const title = computed(() =>
    props.redirect
        ? trans('cms.redirects.edit.title')
        : trans('cms.redirects.create.title'),
);

const breadcrumbs = computed(() => [
    { label: trans('cms.redirects.index.title'), to: index.url() },
    { label: props.redirect?.url_from ?? trans('cms.redirects.create.title') },
]);

function onSubmit() {
    form.submit(
        props.redirect ? update({ redirect: props.redirect.id }) : store(),
    );
}
</script>

<template>
    <EditorLayout
        :title="title"
        :breadcrumbs="breadcrumbs"
        :is-dirty="form.isDirty"
        :processing="form.processing"
        :submit-label="
            redirect ? undefined : trans('cms.redirects.create.submit')
        "
        narrow
        @submit="onSubmit"
    >
        <UPageCard
            variant="subtle"
            :title="trans('cms.redirects.sections.urls')"
        >
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <UFormField
                    :label="trans('cms.redirects.fields.url_from')"
                    :help="trans('cms.redirects.fields.url_from_help')"
                    :error="form.errors.url_from"
                    required
                >
                    <UInput
                        v-model="form.url_from"
                        placeholder="/old-page"
                        class="w-full font-mono"
                    />
                </UFormField>

                <UFormField
                    :label="trans('cms.redirects.fields.url_to')"
                    :help="trans('cms.redirects.fields.url_to_help')"
                    :error="form.errors.url_to"
                    required
                >
                    <UInput
                        v-model="form.url_to"
                        placeholder="/new-page"
                        class="w-full font-mono"
                    />
                </UFormField>
            </div>
        </UPageCard>

        <UPageCard
            variant="subtle"
            :title="trans('cms.redirects.sections.behaviour')"
        >
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <UFormField
                    :help="trans('cms.redirects.fields.is_enabled_help')"
                    :error="form.errors.is_enabled"
                >
                    <USwitch
                        v-model="form.is_enabled"
                        :label="trans('cms.redirects.fields.is_enabled')"
                    />
                </UFormField>

                <UFormField
                    :help="trans('cms.redirects.fields.is_permanent_help')"
                    :error="form.errors.is_permanent"
                >
                    <USwitch
                        v-model="form.is_permanent"
                        :label="trans('cms.redirects.fields.is_permanent')"
                    />
                </UFormField>
            </div>
        </UPageCard>

        <UPageCard
            variant="subtle"
            :title="trans('cms.redirects.sections.schedule')"
        >
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <UFormField
                    :label="trans('cms.redirects.fields.published_at')"
                    :help="trans('cms.redirects.fields.published_at_help')"
                    :error="form.errors.published_at"
                >
                    <UInput
                        v-model="form.published_at"
                        type="datetime-local"
                        class="w-full"
                    />
                </UFormField>

                <UFormField
                    :label="trans('cms.redirects.fields.expired_at')"
                    :help="trans('cms.redirects.fields.expired_at_help')"
                    :error="form.errors.expired_at"
                >
                    <UInput
                        v-model="form.expired_at"
                        type="datetime-local"
                        class="w-full"
                    />
                </UFormField>
            </div>
        </UPageCard>
    </EditorLayout>
</template>
