<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import { useTrans } from '@/composables/useTrans';
import { edit } from '@/routes/appearance';

const { t } = useTrans();

setLayoutProps({
    breadcrumbs: [
        {
            title: t('settings.appearance.head'),
            href: edit(),
        },
    ],
});
</script>

<template>
    <Head :title="t('settings.appearance.head')" />

    <h1 class="sr-only">{{ t('settings.appearance.head') }}</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            :title="t('settings.appearance.head')"
            :description="t('settings.appearance.description')"
        />
        <AppearanceTabs />
    </div>
</template>
