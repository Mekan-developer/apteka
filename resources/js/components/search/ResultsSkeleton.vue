<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';
import { useTrans } from '@/composables/useTrans';

const { t } = useTrans();
</script>

<template>
    <div class="space-y-4" role="status" :aria-label="t('search.loading')">
        <Skeleton class="h-6 w-2/3 max-w-md" />
        <div
            v-for="group in 2"
            :key="group"
            class="space-y-3 rounded-2xl border p-4"
        >
            <Skeleton class="h-6 w-1/2 max-w-sm" />
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="card in 3"
                    :key="card"
                    class="space-y-3 rounded-2xl border p-4"
                    :class="card > 1 ? 'hidden sm:block' : ''"
                >
                    <Skeleton class="h-5 w-1/2" />
                    <Skeleton class="h-8 w-1/3" />
                    <Skeleton class="h-4 w-full" />
                    <Skeleton class="h-4 w-4/5" />
                </div>
            </div>
        </div>
        <span class="sr-only">{{ t('search.loading') }}</span>
    </div>
</template>
