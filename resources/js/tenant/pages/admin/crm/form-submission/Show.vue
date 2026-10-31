<script setup lang="ts">
import { show as showContact } from '@/actions/App/Http/Controllers/Tenant/Admin/Crm/ContactController';
import { index } from '@/actions/App/Http/Controllers/Tenant/Admin/Crm/FormSubmissionController';
import { formatDateTime, userTimezone } from '@/lib/timezone';
import type { FormSubmissionData } from '@/types';

const props = defineProps<{
    submission: { data: FormSubmissionData };
}>();

const { trans } = useTrans();
const timezone = userTimezone(useAuth().value.user);

const submission = computed(() => props.submission.data);

/** The consent questions the form asked, in a consistent order. */
const marketingKeys = computed(() =>
    (
        ['marketing_email', 'marketing_sms', 'marketing_telephone'] as const
    ).filter((key) => key in submission.value.marketing),
);

const title = computed(() =>
    trans('crm.submissions.show.title_with_form', {
        form: submission.value.form.name,
    }),
);

const breadcrumbs = computed(() => [
    { label: trans('crm.submissions.index.title'), to: index.url() },
    { label: title.value },
]);

const contactFact = computed(() => {
    const contact = submission.value.contact;

    if (!contact) {
        return { value: trans('crm.submissions.show.no_contact') };
    }

    if (contact.deleted) {
        return {
            value: `${contact.email} ${trans('crm.submissions.show.deleted')}`,
        };
    }

    return {
        value: contact.name || contact.email,
        href: showContact.url(contact.id),
    };
});

const details = computed(() => [
    {
        label: trans('crm.submissions.columns.form'),
        value: submission.value.form.deleted
            ? `${submission.value.form.name} ${trans('crm.submissions.show.deleted')}`
            : submission.value.form.name,
    },
    {
        label: trans('crm.submissions.columns.submitted_at'),
        value: formatDateTime(submission.value.submitted_at, timezone),
    },
    {
        label: trans('crm.submissions.columns.contact'),
        ...contactFact.value,
    },
    {
        label: trans('crm.submissions.columns.status'),
        value: submission.value.is_spam
            ? trans('crm.submissions.status.spam')
            : trans('crm.submissions.status.genuine'),
        color: submission.value.is_spam ? 'error' : 'success',
    } as const,
]);

const marketing = computed(
    () =>
        marketingKeys.value.map((key) => ({
            label: trans(`crm.contacts.marketing.${key}`),
            value: submission.value.marketing[key]
                ? trans('crm.submissions.show.consented')
                : trans('crm.submissions.show.not_consented'),
            color: submission.value.marketing[key] ? 'success' : 'neutral',
        })) as Array<{
            label: string;
            value: string;
            color: 'success' | 'neutral';
        }>,
);
</script>

<template>
    <RecordLayout
        :title="title"
        :breadcrumbs="breadcrumbs"
        :actions="submission.actions"
    >
        <template v-if="submission.is_spam" #badges>
            <UBadge color="error" variant="subtle">
                {{ trans('crm.submissions.status.spam') }}
            </UBadge>
        </template>

        <UCard variant="subtle">
            <h2 class="text-highlighted mb-1 text-sm font-semibold">
                {{ trans('crm.submissions.show.answers') }}
            </h2>
            <p v-if="!submission.answers.length" class="text-muted text-sm">
                {{ trans('crm.submissions.show.no_answers') }}
            </p>
            <dl v-else class="divide-default divide-y text-sm">
                <div
                    v-for="answer in submission.answers"
                    :key="answer.slug"
                    class="grid grid-cols-1 gap-1 py-3 sm:grid-cols-3"
                >
                    <dt class="text-muted">{{ answer.label }}</dt>
                    <dd class="whitespace-pre-line break-words sm:col-span-2">
                        <template
                            v-if="answer.value !== null && answer.value !== ''"
                        >
                            {{ answer.value }}
                        </template>
                        <span v-else class="text-muted">
                            {{ trans('crm.submissions.show.no_answer') }}
                        </span>
                    </dd>
                </div>
            </dl>
        </UCard>

        <template #sidebar>
            <RecordFacts
                :title="trans('crm.submissions.show.details')"
                :facts="details"
            />
            <RecordFacts
                v-if="marketing.length"
                :title="trans('crm.submissions.show.marketing')"
                :facts="marketing"
            />
            <UCard v-else variant="subtle">
                <h2 class="text-highlighted mb-1 text-sm font-semibold">
                    {{ trans('crm.submissions.show.marketing') }}
                </h2>
                <p class="text-muted text-sm">
                    {{ trans('crm.submissions.show.marketing_none') }}
                </p>
            </UCard>
        </template>
    </RecordLayout>
</template>
