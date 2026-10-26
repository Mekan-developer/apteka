<script setup lang="ts">
import { ChevronDown } from '@lucide/vue';
import { computed } from 'vue';
import PharmacyPriceCard from '@/components/search/PharmacyPriceCard.vue';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { useTrans } from '@/composables/useTrans';
import { formatPrice } from '@/lib/format';
import type { SearchGroup } from '@/types';

const props = defineProps<{
    group: SearchGroup;
}>();

const open = defineModel<boolean>('open', { required: true });

const { t, tc, locale } = useTrans();

/** «Азитромицин · 500 мг · таблетки · №3»: what exactly is compared in this group. */
const title = computed(() =>
    [
        props.group.drug_name,
        props.group.dose_label,
        props.group.dosage_form_label,
        props.group.pack_count === null ? null : `№${props.group.pack_count}`,
    ]
        .filter(Boolean)
        .join(' · '),
);

const cheapest = computed(() => props.group.offers[0]);
</script>

<template>
    <Collapsible
        v-model:open="open"
        as="section"
        class="rounded-2xl border bg-card/50"
    >
        <h3>
            <CollapsibleTrigger
                class="flex min-h-14 w-full items-center gap-3 rounded-2xl px-4 py-3 text-left hover:bg-accent/50 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
            >
                <span class="min-w-0 flex-1">
                    <span
                        class="block text-base leading-snug font-semibold sm:text-lg"
                    >
                        {{ title }}
                    </span>
                    <span
                        class="block text-sm font-normal text-muted-foreground"
                    >
                        {{
                            tc(
                                'search.group_pharmacies',
                                group.pharmacies_count,
                            )
                        }}<template v-if="cheapest">
                            ·
                            {{
                                t('search.from_price', {
                                    price: formatPrice(
                                        cheapest.effective_price,
                                        locale(),
                                    ),
                                })
                            }}</template
                        >
                    </span>
                </span>
                <ChevronDown
                    class="size-5 shrink-0 text-muted-foreground transition-transform duration-200"
                    :class="open ? 'rotate-180' : ''"
                    aria-hidden="true"
                />
            </CollapsibleTrigger>
        </h3>

        <CollapsibleContent>
            <div
                class="grid items-start gap-3 px-4 pb-4 sm:grid-cols-2 lg:grid-cols-3"
            >
                <PharmacyPriceCard
                    v-for="offer in group.offers"
                    :key="offer.id"
                    :offer="offer"
                />
            </div>
        </CollapsibleContent>
    </Collapsible>
</template>
