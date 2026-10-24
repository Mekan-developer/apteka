<script setup lang="ts">
import { Form, Head, setLayoutProps, usePoll } from '@inertiajs/vue3';
import { ChevronDown, Info } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import PriceUploadSheetController from '@/actions/App/Http/Controllers/PriceUploadSheetController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PriceUploadSheetStatusBadge from '@/components/PriceUploadSheetStatusBadge.vue';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Label } from '@/components/ui/label';
import { useTrans } from '@/composables/useTrans';
import { columnName } from '@/lib/excel';
import { index, show } from '@/routes/price-uploads';
import { show as showSheet } from '@/routes/price-uploads/sheets';
import type {
    DiscountRule,
    PriceItem,
    PriceUpload,
    PriceUploadSheetDetail,
} from '@/types';

const props = defineProps<{
    upload: PriceUpload;
    sheet: PriceUploadSheetDetail;
    items: PriceItem[];
    discounts: DiscountRule[];
}>();

const { t } = useTrans();

const helpPoints = [
    'price_uploads.sheet.help.ok',
    'price_uploads.sheet.help.fix',
    'price_uploads.sheet.help.remember',
    'price_uploads.sheet.help.effective_price',
    'price_uploads.sheet.help.search',
];

setLayoutProps({
    breadcrumbs: [
        { title: t('price_uploads.title'), href: index() },
        { title: props.upload.original_filename, href: show(props.upload.id) },
        {
            title: props.sheet.sheet_name,
            href: showSheet({
                price_upload: props.upload.id,
                sheet: props.sheet.id,
            }),
        },
    ],
});

const columnCount = computed(() =>
    Math.max(1, ...props.sheet.preview.map((row) => row.cells.length)),
);
const columns = computed(() =>
    Array.from({ length: columnCount.value }, (_, column) => column),
);

// The selects drive the highlighting of the preview before the form is sent.
const headerRow = ref(
    String(props.sheet.header_row ?? props.sheet.preview[0]?.row ?? ''),
);
const nameCol = ref(String(props.sheet.name_col ?? ''));
const priceCol = ref(String(props.sheet.price_col ?? ''));
const discountedCol = ref(
    props.sheet.price_discounted_col === null
        ? ''
        : String(props.sheet.price_discounted_col),
);

function cellClass(row: number, column: number): string {
    if (String(row) === headerRow.value) {
        return 'bg-primary/10 font-medium';
    }

    if (String(column) === nameCol.value) {
        return 'bg-sky-500/10';
    }

    if (String(column) === priceCol.value) {
        return 'bg-emerald-500/10';
    }

    return String(column) === discountedCol.value ? 'bg-amber-500/10' : '';
}

function discountLabel(discount: DiscountRule): string {
    const parts = [
        t(`price_uploads.discounts.${discount.type}`, {
            percent: Number(discount.percent),
        }),
    ];

    if (discount.min_sum !== null) {
        parts.push(
            t('price_uploads.discounts.from_sum', {
                sum: Number(discount.min_sum).toLocaleString(),
            }),
        );
    } else if (discount.is_conditional) {
        parts.push(t('price_uploads.discounts.conditional'));
    }

    return parts.join(' ');
}

const isReparsing = computed(() => props.sheet.status === 'processing');

const { start, stop } = usePoll(
    2000,
    { only: ['sheet', 'items', 'discounts'] },
    { autoStart: false },
);

watch(isReparsing, (reparsing) => (reparsing ? start() : stop()), {
    immediate: true,
});

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none dark:bg-input/30';
</script>

<template>
    <Head :title="sheet.sheet_name" />

    <div class="flex flex-1 flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="sheet.sheet_name"
                :description="upload.original_filename"
            />
            <PriceUploadSheetStatusBadge :status="sheet.status" />
        </div>

        <Collapsible
            :default-open="true"
            class="rounded-xl border border-sky-500/30 bg-sky-500/5"
            v-slot="{ open }"
        >
            <CollapsibleTrigger
                class="flex w-full items-center gap-2 px-4 py-3 text-left text-sm font-medium"
            >
                <Info class="size-4 shrink-0 text-sky-600 dark:text-sky-400" />
                <span class="flex-1">{{
                    t('price_uploads.sheet.help.title')
                }}</span>
                <ChevronDown
                    class="size-4 shrink-0 text-muted-foreground transition-transform"
                    :class="{ 'rotate-180': open }"
                />
            </CollapsibleTrigger>
            <CollapsibleContent>
                <div class="space-y-2 px-4 pb-4 text-sm text-muted-foreground">
                    <p>
                        {{
                            t('price_uploads.sheet.help.intro', {
                                name: sheet.sheet_name,
                            })
                        }}
                    </p>
                    <ul class="list-disc space-y-1 pl-5">
                        <li v-for="point in helpPoints" :key="point">
                            {{ t(point) }}
                        </li>
                    </ul>
                </div>
            </CollapsibleContent>
        </Collapsible>

        <p v-if="isReparsing" class="text-sm text-muted-foreground">
            {{ t('price_uploads.sheet.reparsing') }}
        </p>
        <p
            v-else-if="sheet.review_reason"
            class="text-sm text-amber-600 dark:text-amber-400"
        >
            {{ t(`price_uploads.review_reasons.${sheet.review_reason}`) }}
        </p>
        <p
            v-else-if="sheet.status === 'failed'"
            class="text-sm text-destructive"
        >
            {{ t('price_uploads.sheet_failed') }}
        </p>

        <section class="space-y-4">
            <Heading
                variant="small"
                :title="t('price_uploads.sheet.preview_title')"
                :description="
                    t('price_uploads.sheet.preview_hint', {
                        name: sheet.sheet_name,
                    })
                "
            />

            <Form
                v-bind="
                    PriceUploadSheetController.update.form({
                        price_upload: upload.id,
                        sheet: sheet.id,
                    })
                "
                :options="{ preserveScroll: true, preserveState: true }"
                class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5 lg:items-end"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="header_row">{{
                        t('price_uploads.sheet.header_row')
                    }}</Label>
                    <select
                        id="header_row"
                        v-model="headerRow"
                        name="header_row"
                        :class="selectClass"
                    >
                        <option
                            v-for="row in sheet.preview"
                            :key="row.row"
                            :value="String(row.row)"
                        >
                            {{ row.row }}
                        </option>
                    </select>
                    <InputError :message="errors.header_row" />
                </div>

                <div class="grid gap-2">
                    <Label for="name_col" class="flex items-center gap-2">
                        <span class="size-2 rounded-full bg-sky-500" />{{
                            t('price_uploads.sheet.name_col')
                        }}
                    </Label>
                    <select
                        id="name_col"
                        v-model="nameCol"
                        name="name_col"
                        :class="selectClass"
                    >
                        <option
                            v-for="column in columns"
                            :key="column"
                            :value="String(column)"
                        >
                            {{ columnName(column) }}
                        </option>
                    </select>
                    <InputError :message="errors.name_col" />
                </div>

                <div class="grid gap-2">
                    <Label for="price_col" class="flex items-center gap-2">
                        <span class="size-2 rounded-full bg-emerald-500" />{{
                            t('price_uploads.sheet.price_col')
                        }}
                    </Label>
                    <select
                        id="price_col"
                        v-model="priceCol"
                        name="price_col"
                        :class="selectClass"
                    >
                        <option
                            v-for="column in columns"
                            :key="column"
                            :value="String(column)"
                        >
                            {{ columnName(column) }}
                        </option>
                    </select>
                    <InputError :message="errors.price_col" />
                </div>

                <div class="grid gap-2">
                    <Label
                        for="price_discounted_col"
                        class="flex items-center gap-2"
                    >
                        <span class="size-2 rounded-full bg-amber-500" />{{
                            t('price_uploads.sheet.price_discounted_col')
                        }}
                    </Label>
                    <select
                        id="price_discounted_col"
                        v-model="discountedCol"
                        name="price_discounted_col"
                        :class="selectClass"
                    >
                        <option value="">
                            {{ t('price_uploads.sheet.no_column') }}
                        </option>
                        <option
                            v-for="column in columns"
                            :key="column"
                            :value="String(column)"
                        >
                            {{ columnName(column) }}
                        </option>
                    </select>
                    <InputError :message="errors.price_discounted_col" />
                </div>

                <Button
                    :disabled="processing || isReparsing"
                    data-test="reparse-sheet-button"
                >
                    {{ t('price_uploads.sheet.reparse') }}
                </Button>
            </Form>

            <div class="overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th class="px-3 py-2 font-medium">
                                {{ t('price_uploads.sheet.row') }}
                            </th>
                            <th
                                v-for="column in columns"
                                :key="column"
                                class="px-3 py-2 font-medium"
                            >
                                {{ columnName(column) }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in sheet.preview"
                            :key="row.row"
                            class="border-t"
                        >
                            <td
                                class="px-3 py-1.5 text-muted-foreground tabular-nums"
                            >
                                {{ row.row }}
                            </td>
                            <td
                                v-for="column in columns"
                                :key="column"
                                class="max-w-64 truncate px-3 py-1.5"
                                :class="cellClass(row.row, column)"
                                :title="String(row.cells[column] ?? '')"
                            >
                                {{ row.cells[column] ?? '' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section v-if="discounts.length" class="space-y-2">
            <Heading
                variant="small"
                :title="t('price_uploads.sheet.discounts_title')"
            />
            <ul class="space-y-1 text-sm">
                <li v-for="discount in discounts" :key="discount.id">
                    <span class="font-medium">{{
                        discountLabel(discount)
                    }}</span>
                    <span class="text-muted-foreground">
                        — {{ discount.raw_text }}</span
                    >
                </li>
            </ul>
        </section>

        <section class="space-y-2">
            <Heading
                variant="small"
                :title="
                    t('price_uploads.sheet.items_title', {
                        count: items.length,
                    })
                "
            />

            <p v-if="items.length === 0" class="text-sm text-muted-foreground">
                {{ t('price_uploads.sheet.no_items') }}
            </p>

            <div v-else class="overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th class="px-3 py-2 font-medium">
                                {{ t('price_uploads.sheet.row') }}
                            </th>
                            <th class="px-3 py-2 font-medium">
                                {{ t('price_uploads.sheet.columns.name') }}
                            </th>
                            <th class="px-3 py-2 font-medium">
                                {{ t('price_uploads.sheet.columns.pack') }}
                            </th>
                            <th class="px-3 py-2 text-right font-medium">
                                {{ t('price_uploads.sheet.columns.price') }}
                            </th>
                            <th class="px-3 py-2 text-right font-medium">
                                {{
                                    t(
                                        'price_uploads.sheet.columns.effective_price',
                                    )
                                }}
                            </th>
                            <th class="px-3 py-2 font-medium">
                                {{
                                    t(
                                        'price_uploads.sheet.columns.goods_discount',
                                    )
                                }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="item in items"
                            :key="item.id"
                            class="border-t"
                        >
                            <td
                                class="px-3 py-1.5 text-muted-foreground tabular-nums"
                            >
                                {{ item.source_row }}
                            </td>
                            <td class="px-3 py-1.5">{{ item.raw_name }}</td>
                            <td class="px-3 py-1.5 tabular-nums">
                                {{ item.pack_count ?? '' }}
                            </td>
                            <td class="px-3 py-1.5 text-right tabular-nums">
                                {{
                                    item.price_discounted
                                        ? `${item.price} / ${item.price_discounted}`
                                        : item.price
                                }}
                            </td>
                            <td
                                class="px-3 py-1.5 text-right font-medium tabular-nums"
                            >
                                {{ item.effective_price }}
                            </td>
                            <td class="px-3 py-1.5 text-muted-foreground">
                                {{
                                    item.goods_discount_percent
                                        ? `${Number(item.goods_discount_percent)}%`
                                        : (item.discount_note ?? '')
                                }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
