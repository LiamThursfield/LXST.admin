<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    index as submissionsIndex,
    show as showSubmission,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Crm/FormSubmissionController';
import { formatDateTime, userTimezone } from '@/lib/timezone';
import type { ContactData, FormSubmissionData } from '@/types';
import ContactForm from './components/ContactForm.vue';

const props = defineProps<{
    contact: { data: ContactData };
    /** The contact's latest submissions, or null if the user can't see submissions. */
    submissions: { data: FormSubmissionData[] } | null;
    submissionsCount: number | null;
}>();

const { trans } = useTrans();
const timezone = userTimezone(useAuth().value.user);

const allSubmissionsUrl = computed(() =>
    submissionsIndex.url({
        query: { 'filter[contact_id]': String(props.contact.data.id) },
    }),
);
</script>

<template>
    <ContactForm :contact="contact.data" :readonly="true">
        <UPageCard
            v-if="submissions"
            variant="subtle"
            :title="trans('crm.submissions.contact_list.title')"
        >
            <p v-if="!submissions.data.length" class="text-muted text-sm">
                {{ trans('crm.submissions.contact_list.empty') }}
            </p>

            <ul v-else class="divide-default divide-y text-sm">
                <li v-for="submission in submissions.data" :key="submission.id">
                    <Link
                        :href="showSubmission.url(submission.id)"
                        class="hover:bg-elevated/50 -mx-2 flex items-center justify-between gap-4 rounded-md px-2 py-2"
                    >
                        <span>{{ submission.form.name }}</span>
                        <span class="flex items-center gap-3">
                            <UBadge
                                v-if="submission.is_spam"
                                color="error"
                                variant="subtle"
                            >
                                {{ trans('crm.submissions.status.spam') }}
                            </UBadge>
                            <span class="text-muted">
                                {{
                                    formatDateTime(
                                        submission.submitted_at,
                                        timezone,
                                    )
                                }}
                            </span>
                        </span>
                    </Link>
                </li>
            </ul>

            <template
                v-if="
                    submissionsCount &&
                    submissionsCount > submissions.data.length
                "
                #footer
            >
                <UButton
                    as="Link"
                    :href="allSubmissionsUrl"
                    color="neutral"
                    variant="subtle"
                    :label="trans('crm.submissions.contact_list.view_all')"
                />
            </template>
        </UPageCard>
    </ContactForm>
</template>
