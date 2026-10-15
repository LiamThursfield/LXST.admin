<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { show as showContact } from '@/actions/App/Http/Controllers/Tenant/Admin/Crm/ContactController';
import RowActions from '@/components/RowActions.vue';
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
</script>

<template>
    <UDashboardPanel>
        <template #header>
            <UDashboardNavbar :title="trans('crm.submissions.show.title')">
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>

                <template #right>
                    <RowActions
                        v-if="submission.actions"
                        :actions="
                            submission.actions.filter(
                                (action) => action.method !== 'get',
                            )
                        "
                    />
                </template>
            </UDashboardNavbar>
        </template>

        <template #body>
            <Head :title="trans('crm.submissions.show.title')" />

            <div class="flex w-full max-w-4xl flex-col gap-6">
                <UPageCard
                    variant="subtle"
                    :title="trans('crm.submissions.show.details')"
                >
                    <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-muted">
                                {{ trans('crm.submissions.columns.form') }}
                            </dt>
                            <dd>
                                {{ submission.form.name }}
                                <span
                                    v-if="submission.form.deleted"
                                    class="text-muted"
                                >
                                    {{ trans('crm.submissions.show.deleted') }}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted">
                                {{
                                    trans(
                                        'crm.submissions.columns.submitted_at',
                                    )
                                }}
                            </dt>
                            <dd>
                                {{
                                    formatDateTime(
                                        submission.submitted_at,
                                        timezone,
                                    )
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted">
                                {{ trans('crm.submissions.columns.contact') }}
                            </dt>
                            <dd>
                                <Link
                                    v-if="
                                        submission.contact &&
                                        !submission.contact.deleted
                                    "
                                    :href="
                                        showContact.url(submission.contact.id)
                                    "
                                    class="text-primary"
                                >
                                    {{
                                        submission.contact.name ||
                                        submission.contact.email
                                    }}
                                </Link>
                                <span v-else-if="submission.contact">
                                    {{ submission.contact.email }}
                                    {{ trans('crm.submissions.show.deleted') }}
                                </span>
                                <span v-else class="text-muted">
                                    {{
                                        trans('crm.submissions.show.no_contact')
                                    }}
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted">
                                {{ trans('crm.submissions.columns.status') }}
                            </dt>
                            <dd>
                                <UBadge
                                    :color="
                                        submission.is_spam ? 'error' : 'success'
                                    "
                                    variant="subtle"
                                >
                                    {{
                                        submission.is_spam
                                            ? trans(
                                                  'crm.submissions.status.spam',
                                              )
                                            : trans(
                                                  'crm.submissions.status.genuine',
                                              )
                                    }}
                                </UBadge>
                            </dd>
                        </div>
                    </dl>
                </UPageCard>

                <UPageCard
                    variant="subtle"
                    :title="trans('crm.submissions.show.answers')"
                >
                    <p
                        v-if="!submission.answers.length"
                        class="text-muted text-sm"
                    >
                        {{ trans('crm.submissions.show.no_answers') }}
                    </p>
                    <dl v-else class="divide-default divide-y text-sm">
                        <div
                            v-for="answer in submission.answers"
                            :key="answer.slug"
                            class="grid grid-cols-1 gap-1 py-3 sm:grid-cols-3"
                        >
                            <dt class="text-muted">{{ answer.label }}</dt>
                            <dd
                                class="whitespace-pre-line break-words sm:col-span-2"
                            >
                                <template
                                    v-if="
                                        answer.value !== null &&
                                        answer.value !== ''
                                    "
                                >
                                    {{ answer.value }}
                                </template>
                                <span v-else class="text-muted">
                                    {{
                                        trans('crm.submissions.show.no_answer')
                                    }}
                                </span>
                            </dd>
                        </div>
                    </dl>
                </UPageCard>

                <UPageCard
                    variant="subtle"
                    :title="trans('crm.submissions.show.marketing')"
                >
                    <p v-if="!marketingKeys.length" class="text-muted text-sm">
                        {{ trans('crm.submissions.show.marketing_none') }}
                    </p>
                    <dl v-else class="flex flex-wrap gap-6 text-sm">
                        <div
                            v-for="key in marketingKeys"
                            :key="key"
                            class="flex items-center gap-2"
                        >
                            <dt>
                                {{ trans(`crm.contacts.marketing.${key}`) }}
                            </dt>
                            <dd>
                                <UBadge
                                    :color="
                                        submission.marketing[key]
                                            ? 'success'
                                            : 'neutral'
                                    "
                                    variant="subtle"
                                >
                                    {{
                                        submission.marketing[key]
                                            ? trans(
                                                  'crm.submissions.show.consented',
                                              )
                                            : trans(
                                                  'crm.submissions.show.not_consented',
                                              )
                                    }}
                                </UBadge>
                            </dd>
                        </div>
                    </dl>
                </UPageCard>
            </div>
        </template>
    </UDashboardPanel>
</template>
