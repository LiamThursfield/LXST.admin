<script setup lang="ts">
import { useForm, useHttp } from '@inertiajs/vue3';
import {
    preview as previewRoute,
    store,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Cms/ContentImportController';
import { index as templatesIndex } from '@/actions/App/Http/Controllers/Tenant/Admin/Cms/TemplateController';

type ImportResult = {
    key: string;
    type_label: string;
    slug: string;
    name: string;
    action: 'create' | 'overwrite' | 'skip' | 'unchanged';
    can_overwrite: boolean;
    changes: string[];
    warnings: string[];
    errors: string[];
};

/**
 * Pasting portable JSON from another environment. Checking it is a dry run that saves nothing, and
 * shows what would be created, changed or skipped. Items that already exist are skipped unless they're
 * chosen to be overwritten, which checks again as that can change the outcome.
 */
const { trans } = useTrans();

const form = useForm({
    json: '',
    overwrite: [] as string[],
});

const check = useHttp({
    json: '',
    overwrite: [] as string[],
});

const results = ref<ImportResult[] | null>(null);
const checkFailed = ref(false);

const jsonError = computed(() => check.errors.json ?? form.errors.json);

const hasErrors = computed(
    () => results.value?.some((item) => item.errors.length > 0) ?? false,
);

const hasWork = computed(
    () =>
        results.value?.some((item) =>
            ['create', 'overwrite'].includes(item.action),
        ) ?? false,
);

// What's been checked no longer applies once the JSON changes
watch(
    () => form.json,
    () => {
        results.value = null;
        form.overwrite = [];
        check.clearErrors();
        form.clearErrors();
    },
);

async function runCheck() {
    checkFailed.value = false;
    check.json = form.json;
    check.overwrite = form.overwrite;

    try {
        const response = (await check.post(previewRoute.url())) as
            { items: ImportResult[] } | undefined;

        // Nothing comes back from a failed validation; check.errors has what went wrong
        results.value = response?.items ?? null;
    } catch {
        checkFailed.value = true;
    }
}

function setOverwrite(key: string, overwrite: boolean) {
    form.overwrite = overwrite
        ? [...form.overwrite, key]
        : form.overwrite.filter((item) => item !== key);

    runCheck();
}

function onSubmit() {
    if (results.value === null) {
        runCheck();
    } else if (hasWork.value && !hasErrors.value) {
        form.post(store.url());
    }
}

/** What overwriting would change; while it's being skipped, that's only a possibility. */
function changesText(item: ImportResult): string {
    const changes = item.changes.join(' · ');

    return item.action === 'skip'
        ? trans('cms.import.if_overwritten', { changes })
        : changes;
}

function actionKey(item: ImportResult): string {
    return item.errors.length ? 'error' : item.action;
}

function actionColor(item: ImportResult) {
    return (
        {
            error: 'error',
            create: 'success',
            overwrite: 'warning',
            skip: 'neutral',
            unchanged: 'neutral',
        } as const
    )[actionKey(item) as 'error' | ImportResult['action']];
}

const breadcrumbs = computed(() => [
    { label: trans('cms.templates.index.title'), to: templatesIndex.url() },
    { label: trans('cms.import.title') },
]);
</script>

<template>
    <EditorLayout
        :title="trans('cms.import.title')"
        :breadcrumbs="breadcrumbs"
        :is-dirty="form.json !== ''"
        :processing="form.processing || check.processing"
        :submit-label="
            results === null
                ? trans('cms.import.check')
                : trans('cms.import.submit')
        "
        :readonly="results !== null && (hasErrors || !hasWork)"
        narrow
        @submit="onSubmit"
    >
        <div class="flex flex-col gap-4">
            <UPageCard variant="subtle">
                <UFormField
                    :label="trans('cms.import.json')"
                    :help="trans('cms.import.json_help')"
                    :error="jsonError"
                    required
                >
                    <UTextarea
                        v-model="form.json"
                        :rows="10"
                        class="w-full font-mono"
                    />
                </UFormField>
                <p v-if="checkFailed" class="text-error text-sm">
                    {{ trans('cms.import.checking_failed') }}
                </p>
            </UPageCard>

            <UPageCard
                v-if="results"
                variant="subtle"
                :title="trans('cms.import.results')"
            >
                <div class="flex flex-col gap-3">
                    <UAlert
                        v-if="hasErrors"
                        color="error"
                        variant="subtle"
                        icon="i-lucide-circle-alert"
                        :description="trans('cms.import.has_errors')"
                    />
                    <UAlert
                        v-else-if="!hasWork"
                        color="neutral"
                        variant="subtle"
                        icon="i-lucide-info"
                        :description="trans('cms.import.nothing_to_do')"
                    />

                    <div
                        v-for="item in results"
                        :key="item.key"
                        class="border-default flex flex-col gap-2 rounded-md border p-3"
                    >
                        <div class="flex flex-wrap items-center gap-2">
                            <UBadge :color="actionColor(item)" variant="subtle">
                                {{
                                    trans(
                                        `cms.import.actions.${actionKey(item)}`,
                                    )
                                }}
                            </UBadge>
                            <span class="text-highlighted font-medium">
                                {{ item.name }}
                            </span>
                            <span class="text-muted font-mono text-sm">
                                {{ item.slug }}
                            </span>
                            <UBadge color="neutral" variant="outline">
                                {{ item.type_label }}
                            </UBadge>
                            <USwitch
                                v-if="item.can_overwrite"
                                class="ms-auto"
                                :model-value="form.overwrite.includes(item.key)"
                                :label="trans('cms.import.overwrite')"
                                :disabled="check.processing"
                                @update:model-value="
                                    setOverwrite(item.key, $event)
                                "
                            />
                        </div>

                        <p
                            v-if="item.changes.length"
                            class="text-muted text-sm"
                        >
                            {{ changesText(item) }}
                        </p>
                        <UAlert
                            v-for="warning in item.warnings"
                            :key="warning"
                            color="warning"
                            variant="subtle"
                            icon="i-lucide-triangle-alert"
                            :description="warning"
                        />
                        <p
                            v-for="error in item.errors"
                            :key="error"
                            class="text-error text-sm"
                        >
                            {{ error }}
                        </p>
                    </div>
                </div>
            </UPageCard>
        </div>
    </EditorLayout>
</template>
