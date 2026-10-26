<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { CalendarDays, Store, TriangleAlert } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import AppearanceToggle from '@/components/AppearanceToggle.vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import LocaleSwitcher from '@/components/LocaleSwitcher.vue';
import EmptyState from '@/components/search/EmptyState.vue';
import GroupFilters from '@/components/search/GroupFilters.vue';
import ProductGroup from '@/components/search/ProductGroup.vue';
import ResultsSkeleton from '@/components/search/ResultsSkeleton.vue';
import ResultsSummary from '@/components/search/ResultsSummary.vue';
import SearchCombobox from '@/components/search/SearchCombobox.vue';
import { TooltipProvider } from '@/components/ui/tooltip';
import { useDrugSearch } from '@/composables/useDrugSearch';
import { useTrans } from '@/composables/useTrans';
import { formatDate } from '@/lib/format';
import { home, login } from '@/routes';
import { index as priceUploads } from '@/routes/price-uploads';
import type { GroupFilter, SearchGroup, SearchResult } from '@/types';

const props = defineProps<{
    query: string;
    minQueryLength: number;
    pricesDate: string | null;
    pricesStale: boolean;
    pharmaciesCount: number;
    popularQueries: string[];
    result: SearchResult;
    suggestions: string[];
}>();

/** More variants than this get filter chips. */
const FILTERS_FROM = 5;

/** The first variants are open, the rest collapsed. */
const OPEN_GROUPS = 3;

const page = usePage();
const { t, tc, locale } = useTrans();

const {
    text,
    suggestions: typedSuggestions,
    loadingSuggestions,
    searching,
    failed,
    tooShort,
    search,
    retry,
    clearSuggestions,
} = useDrugSearch(
    () => props.query,
    () => props.minQueryLength,
);

const emptyFilter = (): GroupFilter => ({ form: null, dose: null, pack: null });
const filter = ref<GroupFilter>(emptyFilter());
const openGroups = ref<Record<string, boolean>>({});

watch(
    () => props.query,
    () => {
        filter.value = emptyFilter();
        openGroups.value = {};
    },
);

const visibleGroups = computed<SearchGroup[]>(() =>
    props.result.groups.filter(
        (group) =>
            (filter.value.form === null ||
                group.dosage_form === filter.value.form) &&
            (filter.value.dose === null || group.dose === filter.value.dose) &&
            (filter.value.pack === null ||
                group.pack_count === filter.value.pack),
    ),
);

function isOpen(group: SearchGroup, index: number): boolean {
    return openGroups.value[group.key] ?? index < OPEN_GROUPS;
}

const state = computed(() => {
    if (searching.value) {
        return 'loading';
    }

    if (failed.value) {
        return 'error';
    }

    if (props.pricesDate === null) {
        return 'no-prices';
    }

    if (props.query === '') {
        return 'welcome';
    }

    return props.result.groups.length === 0 ? 'not-found' : 'results';
});
</script>

<template>
    <Head :title="query || t('search.title')" />

    <TooltipProvider>
        <div class="min-h-screen bg-background text-foreground">
            <a
                href="#results"
                class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:left-2 focus:z-50 focus:rounded-lg focus:bg-primary focus:px-4 focus:py-2 focus:text-primary-foreground"
                >{{ t('search.skip_to_results') }}</a
            >

            <header
                class="mx-auto flex max-w-5xl items-center justify-between gap-2 px-4 pt-4"
            >
                <Link
                    :href="home()"
                    class="flex min-h-11 items-center gap-2 rounded-lg text-lg font-bold tracking-tight focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                >
                    <AppLogoIcon class="size-8" />
                    <span>{{ page.props.name }}</span>
                </Link>
                <div class="flex items-center gap-1 sm:gap-2">
                    <LocaleSwitcher />
                    <AppearanceToggle />
                    <Link
                        :href="page.props.auth.user ? priceUploads() : login()"
                        class="hidden min-h-11 items-center rounded-lg px-3 text-sm text-muted-foreground hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none sm:inline-flex"
                    >
                        {{
                            page.props.auth.user
                                ? t('search.admin')
                                : t('search.sign_in')
                        }}
                    </Link>
                </div>
            </header>

            <main class="mx-auto flex max-w-5xl flex-col gap-6 px-4 pt-6 pb-16">
                <section class="space-y-2">
                    <h1 class="text-2xl font-bold tracking-tight sm:text-3xl">
                        {{ t('search.title') }}
                    </h1>
                    <p class="text-muted-foreground">
                        {{ t('search.description') }}
                    </p>
                </section>

                <div
                    class="sticky top-0 z-20 -mx-4 border-b bg-background/95 px-4 py-3 backdrop-blur supports-[backdrop-filter]:bg-background/85 sm:static sm:mx-0 sm:border-0 sm:bg-transparent sm:p-0 sm:backdrop-blur-none"
                >
                    <SearchCombobox
                        v-model="text"
                        :suggestions="typedSuggestions"
                        :loading="loadingSuggestions"
                        :searching="searching"
                        :too-short="tooShort"
                        :min-length="minQueryLength"
                        :error="page.props.errors.q"
                        @search="search"
                        @close="clearSuggestions"
                    />
                </div>

                <div
                    v-if="pricesDate"
                    class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted-foreground"
                >
                    <span class="inline-flex items-center gap-1.5">
                        <CalendarDays class="size-4" aria-hidden="true" />
                        {{
                            t('search.prices_date', {
                                date: formatDate(pricesDate, locale()),
                            })
                        }}
                    </span>
                    <span
                        v-if="pharmaciesCount"
                        class="inline-flex items-center gap-1.5"
                    >
                        <Store class="size-4" aria-hidden="true" />
                        {{ tc('search.pharmacies_total', pharmaciesCount) }}
                    </span>
                </div>

                <div
                    v-if="pricesStale"
                    role="status"
                    class="flex gap-3 rounded-2xl border border-warning/40 bg-warning/10 p-4 text-sm"
                >
                    <TriangleAlert
                        class="mt-0.5 size-5 shrink-0 text-warning"
                        aria-hidden="true"
                    />
                    <p>{{ t('search.stale') }}</p>
                </div>

                <div id="results" class="scroll-mt-24">
                    <ResultsSkeleton v-if="state === 'loading'" />

                    <EmptyState
                        v-else-if="state === 'error'"
                        variant="error"
                        @retry="retry"
                    />

                    <EmptyState
                        v-else-if="state === 'no-prices'"
                        variant="no-prices"
                    />

                    <EmptyState
                        v-else-if="state === 'welcome'"
                        variant="welcome"
                        :names="popularQueries"
                        @search="search"
                    />

                    <EmptyState
                        v-else-if="state === 'not-found'"
                        variant="not-found"
                        :query="query"
                        :names="suggestions"
                        @search="search"
                    />

                    <div v-else class="space-y-4">
                        <ResultsSummary
                            v-if="result.summary"
                            :query="query"
                            :summary="result.summary"
                            :corrected-to="result.corrected_to"
                            :shown-count="visibleGroups.length"
                        />

                        <GroupFilters
                            v-if="result.groups.length > FILTERS_FROM"
                            v-model="filter"
                            :groups="result.groups"
                        />

                        <ProductGroup
                            v-for="(group, index) in visibleGroups"
                            :key="group.key"
                            :group="group"
                            :open="isOpen(group, index)"
                            @update:open="openGroups[group.key] = $event"
                        />
                    </div>
                </div>
            </main>
        </div>
    </TooltipProvider>
</template>
