<script setup lang="ts">
import { MapPin, Phone, Trophy } from '@lucide/vue';
import { computed, ref } from 'vue';
import DiscountBadge from '@/components/search/DiscountBadge.vue';
import { useTrans } from '@/composables/useTrans';
import { formatPrice } from '@/lib/format';
import type { SearchOffer } from '@/types';

const props = defineProps<{
    offer: SearchOffer;
}>();

const { t, locale } = useTrans();
const expanded = ref(false);

const cheapest = computed(() => props.offer.place === 1);
const discounted = computed(
    () => Number(props.offer.effective_price) < Number(props.offer.price),
);
const difference = computed(() => Number(props.offer.price_difference ?? 0));

/** Long names are cut to two lines; «ещё» shows the rest. */
const longText = computed(
    () =>
        props.offer.raw_name.length + (props.offer.manufacturer?.length ?? 0) >
        70,
);

const discounts = computed(() => props.offer.sheet_discounts ?? []);
const phones = computed(() => props.offer.pharmacy?.phones ?? []);
</script>

<template>
    <article
        class="flex flex-col gap-3 rounded-2xl border bg-card p-4 text-card-foreground"
        :class="
            cheapest ? 'border-2 border-primary shadow-sm' : 'border-border'
        "
        :aria-label="
            t('search.card_label', {
                place: offer.place,
                pharmacy: offer.pharmacy?.name ?? '',
            })
        "
    >
        <div class="flex items-start justify-between gap-2">
            <h4 class="text-base leading-snug font-semibold">
                {{ offer.pharmacy?.name }}
            </h4>
            <span
                v-if="cheapest"
                class="inline-flex shrink-0 items-center gap-1 rounded-full bg-primary px-2.5 py-1 text-xs font-semibold text-primary-foreground"
            >
                <Trophy class="size-3.5" aria-hidden="true" />
                {{ t('search.cheapest') }}
            </span>
            <span
                v-else
                class="shrink-0 rounded-full border px-2.5 py-1 text-xs font-medium text-muted-foreground"
            >
                {{ t('search.place', { place: offer.place }) }}
            </span>
        </div>

        <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
            <p
                class="text-3xl leading-none font-bold tracking-tight tabular-nums"
            >
                {{ formatPrice(offer.effective_price, locale()) }}
                <span class="text-base font-medium text-muted-foreground">{{
                    t('search.manat')
                }}</span>
            </p>
            <p
                v-if="discounted"
                class="text-sm text-muted-foreground tabular-nums"
            >
                <span class="sr-only">{{ t('search.old_price') }}</span>
                <s>{{ formatPrice(offer.price, locale()) }}</s>
            </p>
            <p
                v-if="!cheapest && difference > 0"
                class="text-sm font-medium text-muted-foreground tabular-nums"
            >
                {{
                    t('search.dearer_by', {
                        price: formatPrice(difference, locale()),
                    })
                }}
            </p>
        </div>

        <div class="text-sm">
            <p :class="expanded ? '' : 'line-clamp-2'">
                {{ offer.raw_name
                }}<template v-if="offer.manufacturer">
                    <span class="text-muted-foreground">
                        · {{ offer.manufacturer }}</span
                    ></template
                >
            </p>
            <button
                v-if="longText"
                type="button"
                class="mt-1 min-h-6 text-sm font-medium text-primary underline-offset-4 hover:underline focus-visible:underline focus-visible:outline-none"
                :aria-expanded="expanded"
                @click="expanded = !expanded"
            >
                {{ expanded ? t('search.less') : t('search.more') }}
            </button>
        </div>

        <div
            v-if="offer.goods_discount_percent || discounts.length"
            class="flex flex-wrap gap-2"
        >
            <DiscountBadge
                v-if="offer.goods_discount_percent"
                type="goods"
                :percent="offer.goods_discount_percent"
                :details="offer.discount_note"
            />
            <DiscountBadge
                v-for="discount in discounts"
                :key="discount.id"
                :type="discount.type"
                :percent="discount.percent"
                :min-sum="discount.min_sum"
                :conditional="discount.is_conditional"
                :details="discount.raw_text"
            />
        </div>

        <div
            v-if="offer.pharmacy?.address || phones.length"
            class="space-y-2 border-t pt-3 text-sm"
        >
            <p
                v-if="offer.pharmacy?.address"
                class="flex gap-2 text-muted-foreground"
            >
                <MapPin class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                <span>
                    <span class="sr-only">{{ t('search.address') }}: </span
                    >{{ offer.pharmacy.address }}</span
                >
            </p>
            <div v-if="phones.length" class="flex flex-wrap gap-2">
                <a
                    v-for="phone in phones"
                    :key="phone.dial"
                    :href="`tel:${phone.dial}`"
                    class="inline-flex min-h-11 items-center gap-2 rounded-xl border px-3 font-medium tabular-nums hover:bg-accent focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                    :aria-label="t('search.call', { phone: phone.display })"
                >
                    <Phone class="size-4 text-primary" aria-hidden="true" />
                    {{ phone.display }}
                </a>
            </div>
        </div>
    </article>
</template>
