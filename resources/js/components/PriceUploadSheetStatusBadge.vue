<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import type { BadgeVariants } from '@/components/ui/badge';
import { useTrans } from '@/composables/useTrans';
import type { PriceUploadSheetStatus } from '@/types';

const props = defineProps<{
    status: PriceUploadSheetStatus;
}>();

const { t } = useTrans();

const variants: Record<PriceUploadSheetStatus, BadgeVariants['variant']> = {
    processing: 'outline',
    parsed: 'outline',
    needs_review: 'secondary',
    skipped: 'outline',
    failed: 'destructive',
};

const variant = computed(() => variants[props.status]);
</script>

<template>
    <Badge :variant="variant">{{
        t(`price_uploads.sheet_statuses.${status}`)
    }}</Badge>
</template>
