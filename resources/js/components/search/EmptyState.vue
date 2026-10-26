<script setup lang="ts">
import { CircleAlert, SearchX } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { useTrans } from '@/composables/useTrans';

defineProps<{
    /** welcome: nothing searched yet; not-found: no drug matched; error: the search failed; no-prices: nothing published. */
    variant: 'welcome' | 'not-found' | 'error' | 'no-prices';
    query?: string;
    /** Names to offer: popular queries on the welcome page, close names when nothing was found. */
    names?: string[];
}>();

const emit = defineEmits<{
    search: [query: string];
    retry: [];
}>();

const { t } = useTrans();

const steps = ['search.how.step1', 'search.how.step2', 'search.how.step3'];
</script>

<template>
    <section
        class="space-y-6"
        :aria-live="variant === 'welcome' ? undefined : 'polite'"
    >
        <template v-if="variant === 'welcome'">
            <div v-if="names?.length" class="space-y-3">
                <h2 class="text-sm font-medium text-muted-foreground">
                    {{ t('search.popular') }}
                </h2>
                <div class="flex flex-wrap gap-2">
                    <Button
                        v-for="name in names"
                        :key="name"
                        variant="outline"
                        class="min-h-11 rounded-full px-4 text-base capitalize"
                        @click="emit('search', name)"
                    >
                        {{ name }}
                    </Button>
                </div>
            </div>

            <div class="space-y-3">
                <h2 class="text-lg font-semibold">
                    {{ t('search.how.title') }}
                </h2>
                <ol class="grid gap-3 sm:grid-cols-3">
                    <li
                        v-for="(step, index) in steps"
                        :key="step"
                        class="flex gap-3 rounded-2xl border bg-card p-4"
                    >
                        <span
                            class="flex size-8 shrink-0 items-center justify-center rounded-full bg-primary/10 font-semibold text-primary"
                            aria-hidden="true"
                            >{{ index + 1 }}</span
                        >
                        <span class="text-sm leading-relaxed">{{
                            t(step)
                        }}</span>
                    </li>
                </ol>
            </div>
        </template>

        <div
            v-else
            class="flex flex-col items-center gap-4 rounded-2xl border border-dashed px-4 py-10 text-center"
        >
            <component
                :is="variant === 'error' ? CircleAlert : SearchX"
                class="size-10 text-muted-foreground"
                aria-hidden="true"
            />

            <template v-if="variant === 'error'">
                <p class="text-lg font-semibold">{{ t('search.error') }}</p>
                <Button class="min-h-11 px-6" @click="emit('retry')">
                    {{ t('search.retry') }}
                </Button>
            </template>

            <template v-else-if="variant === 'no-prices'">
                <p class="text-lg font-semibold">{{ t('search.no_prices') }}</p>
            </template>

            <template v-else>
                <div class="space-y-1">
                    <p class="text-lg font-semibold">
                        {{ t('search.nothing_found', { query: query ?? '' }) }}
                    </p>
                    <p class="text-sm text-muted-foreground">
                        {{ t('search.nothing_found_hint') }}
                    </p>
                </div>
                <div v-if="names?.length" class="space-y-2">
                    <p class="text-sm text-muted-foreground">
                        {{ t('search.did_you_mean') }}
                    </p>
                    <div class="flex flex-wrap justify-center gap-2">
                        <Button
                            v-for="name in names"
                            :key="name"
                            variant="outline"
                            class="min-h-11 rounded-full px-4 capitalize"
                            @click="emit('search', name)"
                        >
                            {{ name }}
                        </Button>
                    </div>
                </div>
            </template>
        </div>
    </section>
</template>
