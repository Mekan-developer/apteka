<script setup lang="ts">
import { Info } from '@lucide/vue';
import { computed, ref } from 'vue';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useTrans } from '@/composables/useTrans';
import { formatSum } from '@/lib/format';

const props = defineProps<{
    /** A price discount lowers the price; a goods discount is extra goods for free. */
    type: 'price' | 'goods';
    percent: string | number;
    minSum?: string | null;
    conditional?: boolean;
    /** The discount as the price sheet writes it. */
    details?: string | null;
}>();

const { t, locale } = useTrans();
const open = ref(false);

const label = computed(() => {
    const percent = Number(props.percent);

    if (props.minSum) {
        return t(`search.discount.${props.type}_from_sum`, {
            percent,
            sum: formatSum(props.minSum, locale()),
        });
    }

    if (props.conditional) {
        return t(`search.discount.${props.type}_conditional`, { percent });
    }

    return t(`search.discount.${props.type}`, { percent });
});

const hint = computed(() => {
    const explanation = t(`search.discount.${props.type}_hint`, {
        percent: Number(props.percent),
    });

    return props.details
        ? `${explanation} ${t('search.discount.in_price_list', { text: props.details })}`
        : explanation;
});
</script>

<template>
    <Tooltip v-model:open="open" :delay-duration="150">
        <TooltipTrigger as-child>
            <button
                type="button"
                class="inline-flex min-h-8 items-center gap-1 rounded-full border px-2.5 py-1 text-left text-sm leading-tight focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                :class="
                    type === 'price'
                        ? 'border-primary/30 bg-primary/10 text-foreground'
                        : 'border-border bg-muted text-foreground'
                "
                :aria-label="`${label}. ${hint}`"
                @click="open = !open"
            >
                {{ label }}
                <Info
                    class="size-3.5 shrink-0 text-muted-foreground"
                    aria-hidden="true"
                />
            </button>
        </TooltipTrigger>
        <TooltipContent class="max-w-72 text-sm">
            {{ hint }}
        </TooltipContent>
    </Tooltip>
</template>
