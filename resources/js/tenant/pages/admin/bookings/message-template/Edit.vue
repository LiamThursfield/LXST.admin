<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import {
    index,
    update,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Bookings/MessageTemplateController';

const props = defineProps<{
    event: { value: string; label: string };
    template: {
        is_enabled: boolean;
        email_subject: string | null;
        email_body: string | null;
        sms_body: string | null;
        whatsapp_template_sid: string | null;
    };
    defaults: { email_subject: string; email_body: string; sms_body: string };
    placeholders: string[];
}>();

const { trans } = useTrans();

const form = useForm({
    is_enabled: props.template.is_enabled,
    email_subject: props.template.email_subject ?? '',
    email_body: props.template.email_body ?? '',
    sms_body: props.template.sms_body ?? '',
    whatsapp_template_sid: props.template.whatsapp_template_sid ?? '',
});

const title = computed(() =>
    trans('bookings.messages.edit_title', { event: props.event.label }),
);

const breadcrumbs = computed(() => [
    { label: trans('bookings.messages.title'), to: index.url() },
    { label: title.value },
]);

/** Shown as text, so a brace isn't read as a translation placeholder. */
const placeholderList = computed(() =>
    props.placeholders.map((name) => `{${name}}`).join('  '),
);
</script>

<template>
    <EditorLayout
        :title="title"
        :breadcrumbs="breadcrumbs"
        :is-dirty="form.isDirty"
        :processing="form.processing"
        narrow
        @submit="form.submit(update(event.value))"
    >
        <UPageCard variant="subtle">
            <USwitch
                v-model="form.is_enabled"
                :label="trans('bookings.messages.enabled')"
            />

            <p class="text-muted text-sm">
                {{ trans('bookings.messages.fields.placeholders') }}
                <code class="text-xs">{{ placeholderList }}</code>
            </p>

            <UFormField
                :label="trans('bookings.messages.fields.email_subject')"
                name="email_subject"
                :error="form.errors.email_subject"
            >
                <UInput
                    v-model="form.email_subject"
                    :placeholder="defaults.email_subject"
                    class="w-full"
                />
            </UFormField>

            <UFormField
                :label="trans('bookings.messages.fields.email_body')"
                name="email_body"
                :error="form.errors.email_body"
            >
                <UTextarea
                    v-model="form.email_body"
                    :placeholder="defaults.email_body"
                    :rows="8"
                    class="w-full"
                />
            </UFormField>

            <UFormField
                :label="trans('bookings.messages.fields.sms_body')"
                :help="trans('bookings.messages.fields.sms_help')"
                name="sms_body"
                :error="form.errors.sms_body"
            >
                <UTextarea
                    v-model="form.sms_body"
                    :placeholder="defaults.sms_body"
                    :rows="3"
                    :maxlength="480"
                    class="w-full"
                />
            </UFormField>

            <UFormField
                :label="trans('bookings.messages.fields.whatsapp_template_sid')"
                :help="trans('bookings.messages.fields.whatsapp_help')"
                name="whatsapp_template_sid"
                :error="form.errors.whatsapp_template_sid"
            >
                <UInput v-model="form.whatsapp_template_sid" class="w-full" />
            </UFormField>
        </UPageCard>
    </EditorLayout>
</template>
