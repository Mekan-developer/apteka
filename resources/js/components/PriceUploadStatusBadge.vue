<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import type { BadgeVariants } from '@/components/ui/badge';
import { useTrans } from '@/composables/useTrans';
import type { PriceUploadStatus } from '@/types';

const props = defineProps<{
    status: PriceUploadStatus;
}>();

const { t } = useTrans();

const variants: Record<PriceUploadStatus, BadgeVariants['variant']> = {
    uploaded: 'outline',
    processing: 'outline',
    needs_review: 'secondary',
    active: 'default',
    archived: 'outline',
    failed: 'destructive',
};

const variant = computed(() => variants[props.status]);
</script>

<template>
    <Badge :variant="variant">{{
        t(`price_uploads.statuses.${status}`)
    }}</Badge>
</template>
