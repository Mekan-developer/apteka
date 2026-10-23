<script setup lang="ts">
import { Form, Head, Link, setLayoutProps, usePoll } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import PriceUploadController from '@/actions/App/Http/Controllers/PriceUploadController';
import Heading from '@/components/Heading.vue';
import PriceUploadSheetStatusBadge from '@/components/PriceUploadSheetStatusBadge.vue';
import PriceUploadStatusBadge from '@/components/PriceUploadStatusBadge.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { useTrans } from '@/composables/useTrans';
import { index, show } from '@/routes/price-uploads';
import { show as showSheet } from '@/routes/price-uploads/sheets';
import type { PriceUpload } from '@/types';

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

const isImporting = computed(() =>
    ['uploaded', 'processing'].includes(props.upload.status),
);
const hasSheetInProgress = computed(
    () =>
        props.upload.sheets?.some((sheet) => sheet.status === 'processing') ??
        false,
);
const canActivate = computed(
    () =>
        ['needs_review', 'archived'].includes(props.upload.status) &&
        !hasSheetInProgress.value,
);
const sheetsToReview = computed(
    () =>
        props.upload.sheets?.filter((sheet) => sheet.status === 'needs_review')
            .length ?? 0,
);
const processed = computed(() => props.upload.sheets_processed ?? 0);
const percent = computed(() =>
    props.upload.sheets_total > 0
        ? Math.round((processed.value / props.upload.sheets_total) * 100)
        : 0,
);

// Refresh while the queue works on the file or on a sheet, then stop.
const { start, stop } = usePoll(
    2000,
    { only: ['upload'] },
    { autoStart: false },
);

watch(
    () => isImporting.value || hasSheetInProgress.value,
    (working) => (working ? start() : stop()),
    { immediate: true },
);
</script>

<template>
    <Head :title="upload.original_filename" />

    <div class="flex flex-1 flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                :title="upload.original_filename"
                :description="upload.uploaded_by ?? undefined"
            />

            <div class="flex items-center gap-3">
                <PriceUploadStatusBadge :status="upload.status" />

                <Dialog v-if="canActivate">
                    <DialogTrigger as-child>
                        <Button data-test="activate-upload-button">{{
                            t('price_uploads.activate')
                        }}</Button>
                    </DialogTrigger>
                    <DialogContent>
                        <Form
                            v-bind="
                                PriceUploadController.activate.form(upload.id)
                            "
                            class="space-y-6"
                            v-slot="{ processing }"
                        >
                            <DialogHeader class="space-y-3">
                                <DialogTitle>{{
                                    t('price_uploads.activate_title')
                                }}</DialogTitle>
                                <DialogDescription>{{
                                    t('price_uploads.activate_description')
                                }}</DialogDescription>
                                <p
                                    v-if="sheetsToReview > 0"
                                    class="text-sm text-amber-600 dark:text-amber-400"
                                >
                                    {{
                                        t(
                                            'price_uploads.activate_review_warning',
                                            { count: sheetsToReview },
                                        )
                                    }}
                                </p>
                            </DialogHeader>
                            <DialogFooter class="gap-2">
                                <DialogClose as-child>
                                    <Button variant="secondary">{{
                                        t('price_uploads.cancel')
                                    }}</Button>
                                </DialogClose>
                                <Button type="submit" :disabled="processing">{{
                                    t('price_uploads.activate')
                                }}</Button>
                            </DialogFooter>
                        </Form>
                    </DialogContent>
                </Dialog>
            </div>
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
                        <th class="px-4 py-2" />
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
                            <Link
                                :href="
                                    showSheet({
                                        price_upload: upload.id,
                                        sheet: sheet.id,
                                    })
                                "
                                class="underline-offset-4 hover:underline"
                            >
                                {{ sheet.sheet_name }}
                            </Link>
                        </td>
                        <td class="px-4 py-2">
                            <PriceUploadSheetStatusBadge
                                :status="sheet.status"
                            />
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
                        <td class="px-4 py-2 text-right">
                            <Button
                                v-if="
                                    sheet.status === 'needs_review' ||
                                    sheet.status === 'failed'
                                "
                                variant="outline"
                                size="sm"
                                as-child
                            >
                                <Link
                                    :href="
                                        showSheet({
                                            price_upload: upload.id,
                                            sheet: sheet.id,
                                        })
                                    "
                                >
                                    {{ t('price_uploads.review_sheet') }}
                                </Link>
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
