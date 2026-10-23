<script setup lang="ts">
import { Form, usePage } from '@inertiajs/vue3';
import LocaleController from '@/actions/App/Http/Controllers/LocaleController';
import { Button } from '@/components/ui/button';
import { useTrans } from '@/composables/useTrans';

const page = usePage();
const { t, locale } = useTrans();
</script>

<template>
    <div class="flex items-center gap-1">
        <Form
            v-for="code in page.props.availableLocales"
            :key="code"
            v-bind="LocaleController.form()"
            :options="{ preserveScroll: true }"
            v-slot="{ processing }"
        >
            <input type="hidden" name="locale" :value="code" />
            <Button
                type="submit"
                size="sm"
                :variant="code === locale() ? 'secondary' : 'ghost'"
                :disabled="processing || code === locale()"
            >
                {{ t(`locale.${code}`) }}
            </Button>
        </Form>
    </div>
</template>
