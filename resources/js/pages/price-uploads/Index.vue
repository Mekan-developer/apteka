<script setup lang="ts">
import { Form, Head, Link, setLayoutProps } from '@inertiajs/vue3';
import PriceUploadController from '@/actions/App/Http/Controllers/PriceUploadController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PriceUploadStatusBadge from '@/components/PriceUploadStatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTrans } from '@/composables/useTrans';
import { index, show } from '@/routes/price-uploads';
import type { Paginated, PriceUpload } from '@/types';

defineProps<{
    uploads: Paginated<PriceUpload>;
    maxUploadMegabytes: number;
}>();

const { t, locale } = useTrans();

setLayoutProps({
    breadcrumbs: [{ title: t('price_uploads.title'), href: index() }],
});

function formatDate(date: string | null): string {
    return date ? new Date(date).toLocaleString(locale()) : '';
}
</script>

<template>
    <Head :title="t('price_uploads.title')" />

    <div class="flex flex-1 flex-col gap-6 p-4">
        <Heading
            :title="t('price_uploads.title')"
            :description="t('price_uploads.description')"
        />

        <section class="max-w-xl space-y-4 rounded-xl border p-4">
            <Heading variant="small" :title="t('price_uploads.upload_title')" />

            <Form
                v-bind="PriceUploadController.store.form()"
                reset-on-success
                class="space-y-3"
                v-slot="{ errors, processing, progress }"
            >
                <div class="grid gap-2">
                    <Label for="file">{{
                        t('price_uploads.upload_title')
                    }}</Label>
                    <Input
                        id="file"
                        type="file"
                        name="file"
                        accept=".xlsx"
                        required
                    />
                    <p class="text-sm text-muted-foreground">
                        {{
                            t('price_uploads.file_hint', {
                                size: maxUploadMegabytes,
                            })
                        }}
                    </p>
                    <InputError :message="errors.file" />
                </div>

                <div class="flex items-center gap-4">
                    <Button
                        :disabled="processing"
                        data-test="upload-price-button"
                    >
                        {{ t('price_uploads.upload_button') }}
                    </Button>
                    <span v-if="progress" class="text-sm text-muted-foreground">
                        {{
                            t('price_uploads.uploading', {
                                percent: progress.percentage ?? 0,
                            })
                        }}
                    </span>
                </div>
            </Form>
        </section>

        <section class="space-y-4">
            <Heading
                variant="small"
                :title="t('price_uploads.history_title')"
            />

            <p
                v-if="uploads.data.length === 0"
                class="text-sm text-muted-foreground"
            >
                {{ t('price_uploads.empty') }}
            </p>

            <div v-else class="overflow-x-auto rounded-xl border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th class="px-4 py-2 font-medium">
                                {{ t('price_uploads.columns.date') }}
                            </th>
                            <th class="px-4 py-2 font-medium">
                                {{ t('price_uploads.columns.file') }}
                            </th>
                            <th class="px-4 py-2 font-medium">
                                {{ t('price_uploads.columns.uploaded_by') }}
                            </th>
                            <th class="px-4 py-2 font-medium">
                                {{ t('price_uploads.columns.status') }}
                            </th>
                            <th class="px-4 py-2 text-right font-medium">
                                {{ t('price_uploads.columns.sheets') }}
                            </th>
                            <th class="px-4 py-2 text-right font-medium">
                                {{ t('price_uploads.columns.products') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="upload in uploads.data"
                            :key="upload.id"
                            class="border-t"
                        >
                            <td class="px-4 py-2 whitespace-nowrap">
                                {{ formatDate(upload.created_at) }}
                            </td>
                            <td class="px-4 py-2">
                                <Link
                                    :href="show(upload.id)"
                                    class="font-medium underline-offset-4 hover:underline"
                                >
                                    {{ upload.original_filename }}
                                </Link>
                            </td>
                            <td class="px-4 py-2">{{ upload.uploaded_by }}</td>
                            <td class="px-4 py-2">
                                <PriceUploadStatusBadge
                                    :status="upload.status"
                                />
                            </td>
                            <td class="px-4 py-2 text-right tabular-nums">
                                {{ upload.sheets_processed ?? 0 }} /
                                {{ upload.sheets_total }}
                            </td>
                            <td class="px-4 py-2 text-right tabular-nums">
                                {{ upload.rows_total }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="uploads.links.prev || uploads.links.next"
                class="flex gap-2"
            >
                <Button v-if="uploads.links.prev" variant="outline" as-child>
                    <Link :href="uploads.links.prev">{{
                        t('price_uploads.previous')
                    }}</Link>
                </Button>
                <Button v-if="uploads.links.next" variant="outline" as-child>
                    <Link :href="uploads.links.next">{{
                        t('price_uploads.next')
                    }}</Link>
                </Button>
            </div>
        </section>
    </div>
</template>
