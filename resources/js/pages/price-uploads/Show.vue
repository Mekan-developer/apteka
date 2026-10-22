<script setup lang="ts">
import { Head, setLayoutProps, usePoll } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import PriceUploadStatusBadge from '@/components/PriceUploadStatusBadge.vue';
import { Badge } from '@/components/ui/badge';
import type { BadgeVariants } from '@/components/ui/badge';
import { useTrans } from '@/composables/useTrans';
import { index, show } from '@/routes/price-uploads';
import type { PriceUpload, PriceUploadSheetStatus } from '@/types';

const props = defineProps<{
    upload: PriceUpload;
}>();

const { t } = useTrans();

setLayoutProps({
    breadcrumbs: [
        { title: t('price_uploads.title'), href: index() },
        { title: props.upload.original_filename, href: show(props.upload.id) },
    ],
});

const sheetVariants: Record<PriceUploadSheetStatus, BadgeVariants['variant']> =
    {
        parsed: 'outline',
        needs_review: 'secondary',
        skipped: 'outline',
        failed: 'destructive',
    };

const isImporting = computed(() =>
    ['uploaded', 'processing'].includes(props.upload.status),
);
const processed = computed(() => props.upload.sheets_processed ?? 0);
const percent = computed(() =>
    props.upload.sheets_total > 0
        ? Math.round((processed.value / props.upload.sheets_total) * 100)
        : 0,
);

// Refresh the progress while the queue works on the file, then stop.
const { start, stop } = usePoll(
    2000,
    { only: ['upload'] },
    { autoStart: false },
);

watch(isImporting, (importing) => (importing ? start() : stop()), {
    immediate: true,
});
</script>

<template>
    <Head :title="upload.original_filename" />

    <div class="flex flex-1 flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="upload.original_filename"
                :description="upload.uploaded_by ?? undefined"
            />
            <PriceUploadStatusBadge :status="upload.status" />
        </div>

        <section
            v-if="isImporting"
            class="max-w-xl space-y-2"
            data-test="import-progress"
        >
            <p class="text-sm text-muted-foreground">
                {{
                    upload.sheets_total > 0
                        ? t('price_uploads.progress', {
                              done: processed,
                              total: upload.sheets_total,
                          })
                        : t('price_uploads.waiting')
                }}
            </p>
            <div class="h-2 overflow-hidden rounded-full bg-muted">
                <div
                    class="h-full bg-primary transition-all"
                    :style="{ width: `${percent}%` }"
                />
            </div>
        </section>

        <p
            v-else-if="upload.status === 'failed'"
            class="text-sm text-destructive"
        >
            {{ t('price_uploads.failed') }}
        </p>

        <p v-else class="text-sm text-muted-foreground">
            {{
                t('price_uploads.summary', {
                    ok: upload.sheets_ok,
                    total: upload.sheets_total,
                    products: upload.rows_total,
                })
            }}
        </p>

        <div
            v-if="upload.sheets?.length"
            class="overflow-x-auto rounded-xl border"
        >
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-2 font-medium">№</th>
                        <th class="px-4 py-2 font-medium">
                            {{ t('price_uploads.columns.sheet') }}
                        </th>
                        <th class="px-4 py-2 font-medium">
                            {{ t('price_uploads.columns.status') }}
                        </th>
                        <th class="px-4 py-2 text-right font-medium">
                            {{ t('price_uploads.columns.products') }}
                        </th>
                        <th class="px-4 py-2 font-medium">
                            {{ t('price_uploads.columns.reason') }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="sheet in upload.sheets"
                        :key="sheet.id"
                        class="border-t"
                    >
                        <td
                            class="px-4 py-2 text-muted-foreground tabular-nums"
                        >
                            {{ sheet.sheet_index + 1 }}
                        </td>
                        <td class="px-4 py-2 font-medium">
                            {{ sheet.sheet_name }}
                        </td>
                        <td class="px-4 py-2">
                            <Badge :variant="sheetVariants[sheet.status]">
                                {{
                                    t(
                                        `price_uploads.sheet_statuses.${sheet.status}`,
                                    )
                                }}
                            </Badge>
                        </td>
                        <td class="px-4 py-2 text-right tabular-nums">
                            {{ sheet.rows_parsed }}
                        </td>
                        <td class="px-4 py-2 text-muted-foreground">
                            {{
                                sheet.review_reason
                                    ? t(
                                          `price_uploads.review_reasons.${sheet.review_reason}`,
                                      )
                                    : ''
                            }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
