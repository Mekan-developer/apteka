<script setup lang="ts">
import { Monitor, Moon, Sun } from '@lucide/vue';
import { computed } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useAppearance } from '@/composables/useAppearance';
import type { Appearance } from '@/composables/useAppearance';
import { useTrans } from '@/composables/useTrans';

const { appearance, updateAppearance } = useAppearance();
const { t } = useTrans();

const options = [
    { value: 'light', Icon: Sun },
    { value: 'dark', Icon: Moon },
    { value: 'system', Icon: Monitor },
] as const;

const current = computed(
    () =>
        options.find((option) => option.value === appearance.value) ??
        options[0],
);
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger
            class="inline-flex size-11 items-center justify-center rounded-xl border bg-muted text-muted-foreground hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
            :aria-label="
                t('appearance.label', {
                    current: t(`settings.appearance.${current.value}`),
                })
            "
        >
            <component :is="current.Icon" class="size-5" aria-hidden="true" />
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
            <DropdownMenuRadioGroup
                :model-value="appearance"
                @update:model-value="updateAppearance($event as Appearance)"
            >
                <DropdownMenuRadioItem
                    v-for="{ value, Icon } in options"
                    :key="value"
                    :value="value"
                    class="min-h-11 gap-2"
                >
                    <component :is="Icon" class="size-4" aria-hidden="true" />
                    {{ t(`settings.appearance.${value}`) }}
                </DropdownMenuRadioItem>
            </DropdownMenuRadioGroup>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
