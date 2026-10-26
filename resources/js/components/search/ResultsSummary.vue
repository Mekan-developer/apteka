<script setup lang="ts">
import { useTrans } from '@/composables/useTrans';
import { formatPrice } from '@/lib/format';
import type { SearchResult } from '@/types';

defineProps<{
    query: string;
    summary: NonNullable<SearchResult['summary']>;
    correctedTo: string | null;
    shownCount: number;
}>();

const { t, tc, locale } = useTrans();
</script>

<template>
    <div class="space-y-1" aria-live="polite" aria-atomic="true">
        <p v-if="correctedTo" class="text-sm text-muted-foreground">
            {{ t('search.corrected', { name: correctedTo, query }) }}
        </p>
        <p class="text-base sm:text-lg">
            {{ tc('search.summary_variants', summary.variants_count) }}
            {{ tc('search.summary_pharmacies', summary.pharmacies_count)
            }}<template v-if="summary.lowest_price"
                >&nbsp;·
                <span class="font-semibold whitespace-nowrap">{{
                    t('search.summary_lowest', {
                        price: formatPrice(summary.lowest_price, locale()),
                    })
                }}</span></template
            >
        </p>
        <p
            v-if="shownCount < summary.variants_count"
            class="text-sm text-muted-foreground"
        >
            {{
                t('search.filtered', {
                    shown: shownCount,
                    total: summary.variants_count,
                })
            }}
        </p>
    </div>
</template>
