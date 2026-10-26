<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useTrans } from '@/composables/useTrans';
import type { GroupFilter, SearchGroup } from '@/types';

const props = defineProps<{
    groups: SearchGroup[];
}>();

const filter = defineModel<GroupFilter>({ required: true });

const { t } = useTrans();

/** More chips than this in one row hide behind «ещё». */
const VISIBLE_CHIPS = 6;

type Dimension = keyof GroupFilter;

type Chip = {
    value: string | number;
    label: string;
    count: number;
};

/** The values of one dimension among the groups, the most common first. */
function chips(
    groups: SearchGroup[],
    value: (group: SearchGroup) => string | number | null,
    label: (group: SearchGroup) => string | null,
): Chip[] {
    const found = new Map<string | number, Chip>();

    for (const group of groups) {
        const key = value(group);

        if (key === null) {
            continue;
        }

        const chip = found.get(key) ?? {
            value: key,
            label: label(group) ?? String(key),
            count: 0,
        };
        chip.count++;
        found.set(key, chip);
    }

    return [...found.values()].sort((a, b) => b.count - a.count);
}

const matchesForm = (group: SearchGroup): boolean =>
    filter.value.form === null || group.dosage_form === filter.value.form;
const matchesDose = (group: SearchGroup): boolean =>
    filter.value.dose === null || group.dose === filter.value.dose;

// Each row narrows the next: the doses of the chosen form, the pack sizes of the chosen form and dose.
const rows = computed(() =>
    (
        [
            [
                'form',
                t('search.filter.form'),
                chips(
                    props.groups,
                    (group) => group.dosage_form,
                    (group) => group.dosage_form_label,
                ),
            ],
            [
                'dose',
                t('search.filter.dose'),
                chips(
                    props.groups.filter(matchesForm),
                    (group) => group.dose,
                    (group) => group.dose_label,
                ),
            ],
            [
                'pack',
                t('search.filter.pack'),
                chips(
                    props.groups.filter(
                        (group) => matchesForm(group) && matchesDose(group),
                    ),
                    (group) => group.pack_count,
                    (group) =>
                        group.pack_count === null
                            ? null
                            : `№${group.pack_count}`,
                ).sort((a, b) => Number(a.value) - Number(b.value)),
            ],
        ] as [Dimension, string, Chip[]][]
    ).filter(([, , values]) => values.length > 1),
);

// A choice the narrower rows no longer offer is dropped, so the filter never hides every group.
watch(rows, (current) => {
    for (const dimension of ['dose', 'pack'] as const) {
        const row = current.find(([name]) => name === dimension);

        if (
            filter.value[dimension] !== null &&
            !row?.[2].some((chip) => chip.value === filter.value[dimension])
        ) {
            filter.value = { ...filter.value, [dimension]: null };
        }
    }
});

const expanded = ref<Partial<Record<Dimension, boolean>>>({});

function visible(dimension: Dimension, values: Chip[]): Chip[] {
    if (expanded.value[dimension] || values.length <= VISIBLE_CHIPS) {
        return values;
    }

    const shown = values.slice(0, VISIBLE_CHIPS);
    const selected = values.find(
        (chip) => chip.value === filter.value[dimension],
    );

    return selected && !shown.includes(selected) ? [...shown, selected] : shown;
}

function choose(dimension: Dimension, value: string | number | null): void {
    filter.value = {
        ...filter.value,
        [dimension]: filter.value[dimension] === value ? null : value,
    };
}

function chipClass(selected: boolean): string {
    return selected
        ? 'border-primary bg-primary text-primary-foreground'
        : 'border-border bg-card hover:bg-accent';
}
</script>

<template>
    <div v-if="rows.length" class="space-y-3">
        <div
            v-for="[dimension, label, values] in rows"
            :key="dimension"
            role="group"
            :aria-label="label"
            class="-mx-4 flex items-center gap-2 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:overflow-visible sm:px-0 sm:pb-0"
        >
            <span class="shrink-0 pr-1 text-sm text-muted-foreground" aria-hidden="true"
                >{{ label }}:</span
            >
            <button
                type="button"
                class="min-h-11 shrink-0 rounded-full border px-4 text-sm font-medium transition-colors focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                :class="chipClass(filter[dimension] === null)"
                :aria-pressed="filter[dimension] === null"
                @click="choose(dimension, null)"
            >
                {{ t('search.filter.all') }}
            </button>
            <button
                v-for="chip in visible(dimension, values)"
                :key="chip.value"
                type="button"
                class="min-h-11 shrink-0 rounded-full border px-4 text-sm font-medium transition-colors focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                :class="chipClass(filter[dimension] === chip.value)"
                :aria-pressed="filter[dimension] === chip.value"
                @click="choose(dimension, chip.value)"
            >
                {{ chip.label }}
                <span class="ml-1 opacity-70">{{ chip.count }}</span>
            </button>
            <button
                v-if="values.length > VISIBLE_CHIPS"
                type="button"
                class="min-h-11 shrink-0 rounded-full px-3 text-sm font-medium text-primary underline-offset-4 hover:underline focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                :aria-expanded="expanded[dimension] ?? false"
                @click="expanded[dimension] = !expanded[dimension]"
            >
                {{
                    expanded[dimension]
                        ? t('search.less')
                        : t('search.more_count', {
                              count: values.length - VISIBLE_CHIPS,
                          })
                }}
            </button>
        </div>
    </div>
</template>
