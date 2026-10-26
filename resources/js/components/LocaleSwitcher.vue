<script setup lang="ts">
import { Form, usePage } from '@inertiajs/vue3';
import LocaleController from '@/actions/App/Http/Controllers/LocaleController';
import { useTrans } from '@/composables/useTrans';

const page = usePage();
const { t, locale } = useTrans();
</script>

<template>
    <div
        role="group"
        :aria-label="t('locale.label')"
        class="inline-flex h-11 items-center gap-0.5 rounded-xl border bg-muted p-1"
    >
        <Form
            v-for="code in page.props.availableLocales"
            :key="code"
            v-bind="LocaleController.form()"
            :options="{ preserveScroll: true, preserveState: true }"
            v-slot="{ processing }"
        >
            <input type="hidden" name="locale" :value="code" />
            <button
                type="submit"
                :lang="code"
                :aria-pressed="code === locale()"
                :aria-label="t(`locale.${code}`)"
                :title="t(`locale.${code}`)"
                :disabled="processing"
                class="h-9 min-w-11 rounded-lg px-2.5 text-sm font-semibold uppercase transition-colors focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                :class="
                    code === locale()
                        ? 'bg-background text-foreground shadow-sm ring-1 ring-border'
                        : 'text-muted-foreground hover:text-foreground'
                "
            >
                {{ code }}
            </button>
        </Form>
    </div>
</template>
