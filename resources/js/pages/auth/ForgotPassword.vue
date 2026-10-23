<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTrans } from '@/composables/useTrans';
import { login } from '@/routes';
import { email } from '@/routes/password';

defineProps<{
    status?: string;
}>();

const { t } = useTrans();

setLayoutProps({
    title: t('auth.forgot.title'),
    description: t('auth.forgot.description'),
});
</script>

<template>
    <Head :title="t('auth.forgot.title')" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ status }}
    </div>

    <div class="space-y-6">
        <Form v-bind="email.form()" v-slot="{ errors, processing }">
            <div class="grid gap-2">
                <Label for="email">{{ t('common.email') }}</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    autocomplete="off"
                    v-focus
                    placeholder="email@example.com"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="my-6 flex items-center justify-start">
                <Button
                    class="w-full"
                    :disabled="processing"
                    data-test="email-password-reset-link-button"
                >
                    <Spinner v-if="processing" />
                    {{ t('auth.forgot.submit') }}
                </Button>
            </div>
        </Form>

        <div class="text-center text-sm text-muted-foreground">
            <TextLink :href="login()">{{
                t('auth.forgot.back_to_login')
            }}</TextLink>
        </div>
    </div>
</template>
