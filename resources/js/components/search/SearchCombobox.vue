<script setup lang="ts">
import { Search } from '@lucide/vue';
import { computed, ref, useId, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTrans } from '@/composables/useTrans';
import type { DrugSuggestion } from '@/types';

const props = defineProps<{
    suggestions: DrugSuggestion[];
    loading: boolean;
    searching: boolean;
    tooShort: boolean;
    minLength: number;
    error?: string;
}>();

const text = defineModel<string>({ required: true });

const emit = defineEmits<{
    search: [query?: string];
    close: [];
}>();

const { t } = useTrans();
const id = useId();
const listboxId = `${id}-listbox`;
const hintId = `${id}-hint`;
const activeIndex = ref(-1);
const dismissed = ref(false);

const open = computed(() => !dismissed.value && props.suggestions.length > 0);

watch(
    () => props.suggestions,
    () => {
        activeIndex.value = -1;
        dismissed.value = false;
    },
);

function optionId(index: number): string {
    return `${id}-option-${index}`;
}

function move(step: number): void {
    if (!open.value) {
        dismissed.value = false;

        return;
    }

    // -1 is the typed text itself: the list wraps through it, as in browser address bars.
    const next = activeIndex.value + step;
    const last = props.suggestions.length - 1;

    activeIndex.value = next > last ? -1 : next < -1 ? last : next;
}

function choose(suggestion: DrugSuggestion): void {
    emit('search', suggestion.name);
}

function submit(): void {
    const active = props.suggestions[activeIndex.value];

    if (open.value && active) {
        choose(active);

        return;
    }

    emit('search');
}

function close(): void {
    dismissed.value = true;
    activeIndex.value = -1;
    emit('close');
}

/** The typed letters in the suggested name, found ignoring case and «ё». */
function parts(name: string): [string, string, string] {
    const typed = text.value.trim().toLowerCase().replaceAll('ё', 'е');
    const start =
        typed === ''
            ? -1
            : name.toLowerCase().replaceAll('ё', 'е').indexOf(typed);

    if (start === -1) {
        return [name, '', ''];
    }

    return [
        name.slice(0, start),
        name.slice(start, start + typed.length),
        name.slice(start + typed.length),
    ];
}

const describedBy = computed(() =>
    props.tooShort || props.error ? hintId : undefined,
);
</script>

<template>
    <div class="space-y-1">
        <form
            role="search"
            class="relative flex gap-2"
            @submit.prevent="submit"
        >
            <div class="relative min-w-0 flex-1">
                <label :for="`${id}-input`" class="sr-only">{{
                    t('search.label')
                }}</label>
                <Search
                    class="pointer-events-none absolute top-1/2 left-4 size-5 -translate-y-1/2 text-muted-foreground"
                    aria-hidden="true"
                />
                <input
                    :id="`${id}-input`"
                    v-model="text"
                    type="search"
                    name="q"
                    role="combobox"
                    aria-autocomplete="list"
                    :aria-expanded="open"
                    :aria-controls="listboxId"
                    :aria-activedescendant="
                        open && activeIndex >= 0
                            ? optionId(activeIndex)
                            : undefined
                    "
                    :aria-describedby="describedBy"
                    :aria-invalid="error ? true : undefined"
                    :placeholder="t('search.placeholder')"
                    autocomplete="off"
                    autocapitalize="off"
                    spellcheck="false"
                    enterkeyhint="search"
                    maxlength="100"
                    autofocus
                    class="h-12 w-full min-w-0 appearance-none rounded-xl border border-input bg-card pr-10 pl-12 text-base shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/40 aria-invalid:border-destructive sm:text-lg [&::-webkit-search-cancel-button]:hidden"
                    @keydown.down.prevent="move(1)"
                    @keydown.up.prevent="move(-1)"
                    @keydown.esc="close"
                    @blur="close"
                />
                <Spinner
                    v-if="loading"
                    class="absolute top-1/2 right-4 -translate-y-1/2 text-muted-foreground"
                    aria-hidden="true"
                />

                <ul
                    v-show="open"
                    :id="listboxId"
                    role="listbox"
                    :aria-label="t('search.suggestions')"
                    class="absolute inset-x-0 top-full z-30 mt-2 max-h-80 overflow-auto rounded-xl border bg-popover p-1 text-popover-foreground shadow-lg"
                >
                    <li
                        v-for="(suggestion, index) in suggestions"
                        :id="optionId(index)"
                        :key="suggestion.name"
                        role="option"
                        :aria-selected="index === activeIndex"
                        class="flex min-h-11 cursor-pointer items-center justify-between gap-4 rounded-lg px-3 text-base"
                        :class="
                            index === activeIndex
                                ? 'bg-accent text-accent-foreground'
                                : 'hover:bg-accent/60'
                        "
                        @mousedown.prevent="choose(suggestion)"
                        @mousemove="activeIndex = index"
                    >
                        <span class="truncate"
                            >{{ parts(suggestion.name)[0]
                            }}<mark
                                class="bg-transparent font-semibold text-foreground"
                                >{{ parts(suggestion.name)[1] }}</mark
                            >{{ parts(suggestion.name)[2] }}</span
                        >
                        <span class="shrink-0 text-sm text-muted-foreground">{{
                            t('search.suggestion_pharmacies', {
                                count: suggestion.pharmacies_count,
                            })
                        }}</span>
                    </li>
                </ul>
            </div>

            <Button
                type="submit"
                class="h-12 shrink-0 rounded-xl px-5 text-base sm:px-8"
                :disabled="searching"
                data-test="search-button"
            >
                <Spinner v-if="searching" aria-hidden="true" />
                {{ t('search.button') }}
            </Button>
        </form>

        <p
            v-if="tooShort || error"
            :id="hintId"
            class="px-1 text-sm"
            :class="error ? 'text-destructive' : 'text-muted-foreground'"
        >
            {{ error ?? t('search.too_short', { count: minLength }) }}
        </p>
    </div>
</template>
