<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTrans } from '@/composables/useTrans';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

defineProps<{
    status?: string;
}>();

const { t } = useTrans();

setLayoutProps({
    title: t('auth.verify.title'),
    description: t('auth.verify.description'),
});
</script>

<template>
    <Head :title="t('auth.verify.title')" />

    <div
        v-if="status === 'verification-link-sent'"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ t('auth.verify.sent') }}
    </div>

    <Form
        v-bind="send.form()"
        class="space-y-6 text-center"
        v-slot="{ processing }"
    >
        <Button :disabled="processing" variant="secondary">
            <Spinner v-if="processing" />
            {{ t('auth.verify.resend') }}
        </Button>

        <TextLink :href="logout()" as="button" class="mx-auto block text-sm">
            {{ t('common.log_out') }}
        </TextLink>
    </Form>
</template>
