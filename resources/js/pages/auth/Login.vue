<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Mail } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useTrans } from '@/composables/useTrans';
import AuthSplitLayout from '@/layouts/auth/AuthSplitLayout.vue';
import { store } from '@/routes/login';

defineOptions({ layout: AuthSplitLayout });

const { t } = useTrans();
</script>

<template>
    <Head :title="t('auth.login.head')" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="grid gap-5"
    >
        <div class="grid gap-2">
            <Label for="email" class="text-slate-200">
                {{ t('common.email') }}
            </Label>
            <div class="relative">
                <Mail
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400"
                    aria-hidden="true"
                />
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    v-focus
                    :tabindex="1"
                    autocomplete="email"
                    placeholder="email@example.com"
                    :aria-invalid="errors.email ? true : undefined"
                    class="h-11 rounded-lg border-white/15 bg-slate-950/40 pl-10 focus-visible:border-emerald-500 focus-visible:ring-emerald-500/30"
                />
            </div>
            <InputError :message="errors.email" />
        </div>

        <div class="grid gap-2">
            <Label for="password" class="text-slate-200">
                {{ t('common.password') }}
            </Label>
            <PasswordInput
                id="password"
                name="password"
                required
                :tabindex="2"
                autocomplete="current-password"
                :placeholder="t('common.password')"
                :aria-invalid="errors.password ? true : undefined"
                class="h-11 rounded-lg border-white/15 bg-slate-950/40 focus-visible:border-emerald-500 focus-visible:ring-emerald-500/30"
            />
            <InputError :message="errors.password" />
        </div>

        <Label
            for="remember"
            class="flex w-fit items-center gap-3 font-normal text-slate-200"
        >
            <Checkbox
                id="remember"
                name="remember"
                :tabindex="3"
                class="size-5 border-white/25 data-[state=checked]:border-emerald-500 data-[state=checked]:bg-emerald-500 data-[state=checked]:text-white"
            />
            <span>{{ t('auth.login.remember') }}</span>
        </Label>

        <Button
            type="submit"
            class="mt-1 h-11 w-full rounded-lg bg-emerald-600 text-base text-white hover:bg-emerald-500 focus-visible:ring-emerald-500/40"
            :tabindex="4"
            :disabled="processing"
            data-test="login-button"
        >
            <Spinner v-if="processing" />
            {{ t('auth.login.submit') }}
        </Button>
    </Form>
</template>
