<script setup lang="ts">
import { store as exchangeToken } from '@/actions/App/Http/Controllers/Tenant/Api/V1/Dj/GuestTokenController';
import { PublicApiError, publicApi } from '@/lib/publicApi';

const props = defineProps<{
    /** The song list's public ID */
    listId: string;
}>();

const emit = defineEmits<{ token: [token: string] }>();

const { trans } = useTrans();
const password = ref('');
const error = ref<string | null>(null);
const busy = ref(false);

async function submit() {
    busy.value = true;
    error.value = null;

    try {
        const response = await publicApi<{ data: { token: string } }>(
            exchangeToken(props.listId),
            { body: { password: password.value } },
        );
        emit('token', response.data.token);
    } catch (exception) {
        error.value =
            exception instanceof PublicApiError
                ? (exception.body.errors?.password?.[0] ?? exception.message)
                : trans('dj.hosted.error');
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <UPageCard
        :title="trans('dj.hosted.pass.title')"
        :description="trans('dj.hosted.pass.help')"
        variant="subtle"
    >
        <form class="flex flex-col gap-3" @submit.prevent="submit">
            <UFormField
                :label="trans('dj.hosted.pass.password')"
                :error="error ?? undefined"
            >
                <UInput
                    v-model="password"
                    size="xl"
                    class="w-full"
                    autocomplete="off"
                    autofocus
                />
            </UFormField>
            <UButton
                type="submit"
                size="xl"
                block
                :loading="busy"
                :disabled="!password"
                :label="trans('dj.hosted.pass.submit')"
            />
        </form>
    </UPageCard>
</template>
