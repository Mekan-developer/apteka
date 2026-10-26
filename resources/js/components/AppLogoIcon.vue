<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <!-- A medical cross on the accent colour; the square follows currentColor (text-primary by default). -->
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 32 32"
        class="text-primary"
        :class="className"
        aria-hidden="true"
        v-bind="$attrs"
    >
        <rect width="32" height="32" rx="9" fill="currentColor" />
        <path
            d="M13 7.5a1.5 1.5 0 0 1 1.5-1.5h3A1.5 1.5 0 0 1 19 7.5V13h5.5a1.5 1.5 0 0 1 1.5 1.5v3a1.5 1.5 0 0 1-1.5 1.5H19v5.5a1.5 1.5 0 0 1-1.5 1.5h-3a1.5 1.5 0 0 1-1.5-1.5V19H7.5A1.5 1.5 0 0 1 6 17.5v-3A1.5 1.5 0 0 1 7.5 13H13V7.5Z"
            style="fill: var(--primary-foreground)"
        />
    </svg>
</template>
