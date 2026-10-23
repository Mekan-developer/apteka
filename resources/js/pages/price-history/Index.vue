<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTrans } from '@/composables/useTrans';
import { priceHistory } from '@/routes';
import type { PriceHistoryRow, PriceUpload, SearchOffer } from '@/types';

const props = defineProps<{
    query: string;
    uploads: PriceUpload[];
    rows: PriceHistoryRow[];
}>();

const { t, locale } = useTrans();

setLayoutProps({
    breadcrumbs: [{ title: t('price_history.title'), href: priceHistory() }],
});

// Oldest on the left, so the price reads as a timeline.
const columns = computed(() => [...props.uploads].reverse());

function formatDate(date: string | null): string {
    return date ? new Date(date).toLocaleDateString(locale()) : '';
}

function formatPrice(price: string): string {
    return Number(price).toLocaleString(locale(), {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function offerIn(
    row: PriceHistoryRow,
    uploadId: number,
): SearchOffer | undefined {
    return row.prices.find((price) => price.upload_id === uploadId)?.offer;
}

/** The change against the previous upload in which the drug was on sale. */
function change(row: PriceHistoryRow, columnIndex: number): number | null {
    const current = offerIn(row, columns.value[columnIndex].id);

    for (let previous = columnIndex - 1; previous >= 0; previous--) {
        const earlier = offerIn(row, columns.value[previous].id);

        if (earlier && current) {
            return (
                Number(current.effective_price) -
                Number(earlier.effective_price)
            );
        }
    }

    return null;
}
</script>

<template>
    <Head :title="t('price_history.title')" />

    <div class="flex flex-1 flex-col gap-6 p-4">
        <Heading
            :title="t('price_history.title')"
            :description="t('price_history.description')"
        />

        <Form
            v-bind="priceHistory.form()"
            class="flex max-w-xl flex-col gap-2 sm:flex-row sm:items-end"
            v-slot="{ errors, processing }"
        >
            <div class="grid flex-1 gap-2">
                <Label for="q" class="sr-only">{{ t('search.label') }}</Label>
                <Input
                    id="q"
                    name="q"
                    type="search"
                    :default-value="query"
                    :placeholder="t('price_history.placeholder')"
                    maxlength="100"
                    required
                    autocomplete="off"
                />
                <InputError :message="errors.q" />
            </div>
            <Button type="submit" :disabled="processing">{{
                t('price_history.button')
            }}</Button>
        </Form>

        <template v-if="query">
            <p
                v-if="uploads.length === 0"
                class="text-sm text-muted-foreground"
            >
                {{ t('price_history.no_uploads') }}
            </p>
            <p
                v-else-if="rows.length === 0"
                class="text-sm text-muted-foreground"
            >
                {{ t('price_history.nothing_found', { query }) }}
            </p>

            <template v-else>
                <p class="text-sm text-muted-foreground">
                    {{ t('price_history.hint') }}
                </p>

                <div class="overflow-x-auto rounded-xl border">
                    <table class="w-full text-sm">
                        <thead
                            class="bg-muted/50 text-left text-muted-foreground"
                        >
                            <tr>
                                <th class="px-4 py-2 font-medium">
                                    {{ t('price_history.pack') }}
                                </th>
                                <th
                                    v-for="upload in columns"
                                    :key="upload.id"
                                    class="px-4 py-2 text-right font-medium whitespace-nowrap"
                                >
                                    {{ formatDate(upload.activated_at) }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in rows"
                                :key="row.pack_count ?? 'none'"
                                class="border-t align-top"
                            >
                                <td
                                    class="px-4 py-2 font-medium whitespace-nowrap"
                                >
                                    {{
                                        row.pack_count === null
                                            ? t('price_history.no_pack')
                                            : `№${row.pack_count}`
                                    }}
                                </td>
                                <td
                                    v-for="(upload, columnIndex) in columns"
                                    :key="upload.id"
                                    class="px-4 py-2 text-right"
                                >
                                    <template v-if="offerIn(row, upload.id)">
                                        <div
                                            class="flex items-center justify-end gap-1 font-medium tabular-nums"
                                        >
                                            <span
                                                v-if="
                                                    (change(row, columnIndex) ??
                                                        0) > 0
                                                "
                                                class="text-destructive"
                                                aria-hidden="true"
                                                >▲</span
                                            >
                                            <span
                                                v-else-if="
                                                    (change(row, columnIndex) ??
                                                        0) < 0
                                                "
                                                class="text-emerald-600 dark:text-emerald-400"
                                                aria-hidden="true"
                                                >▼</span
                                            >
                                            {{
                                                formatPrice(
                                                    offerIn(row, upload.id)
                                                        ?.effective_price ??
                                                        '0',
                                                )
                                            }}
                                        </div>
                                        <div
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{
                                                offerIn(row, upload.id)
                                                    ?.pharmacy?.name
                                            }}
                                        </div>
                                    </template>
                                    <span
                                        v-else
                                        class="text-xs text-muted-foreground"
                                        >{{
                                            t('price_history.not_on_sale')
                                        }}</span
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </template>
    </div>
</template>
