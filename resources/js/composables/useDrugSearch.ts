import { router, useHttp } from '@inertiajs/vue3';
import type { ComputedRef, Ref } from 'vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import DrugSuggestionController from '@/actions/App/Http/Controllers/Api/V1/DrugSuggestionController';
import { useTrans } from '@/composables/useTrans';
import { notify } from '@/lib/notifier';
import { home } from '@/routes';
import type { DrugSuggestion } from '@/types';

/** Pause in typing after which the search box asks for names. */
const SUGGEST_DELAY_MS = 250;

export type UseDrugSearchReturn = {
    /** What is typed in the search box. */
    text: Ref<string>;
    suggestions: Ref<DrugSuggestion[]>;
    loadingSuggestions: Ref<boolean>;
    /** A search visit is on its way: the results show a skeleton. */
    searching: Ref<boolean>;
    /** The last search did not reach the server or the server failed. */
    failed: Ref<boolean>;
    tooShort: ComputedRef<boolean>;
    search: (query?: string) => void;
    retry: () => void;
    clearSuggestions: () => void;
};

/**
 * The search box state: names suggested while typing (debounced) and the search itself,
 * which is a visit to ?q=… so the URL can be shared and the back button works.
 */
export function useDrugSearch(
    currentQuery: () => string,
    minLength: () => number,
): UseDrugSearchReturn {
    const { t } = useTrans();
    const text = ref(currentQuery());
    const suggestions = ref<DrugSuggestion[]>([]);
    const loadingSuggestions = ref(false);
    const searching = ref(false);
    const failed = ref(false);
    const lastQuery = ref(currentQuery());
    const http = useHttp<{ q: string }, { data: DrugSuggestion[] }>({
        q: '',
    });
    let timer: ReturnType<typeof setTimeout> | undefined;

    const tooShort = computed(
        () =>
            text.value.trim().length > 0 &&
            text.value.trim().length < minLength(),
    );

    // Back and forward buttons change the query of the page, the box follows it.
    watch(currentQuery, (query) => {
        text.value = query;
        lastQuery.value = query;
    });

    function clearSuggestions(): void {
        clearTimeout(timer);
        http.cancel();
        suggestions.value = [];
        loadingSuggestions.value = false;
    }

    async function loadSuggestions(typed: string): Promise<void> {
        http.cancel();
        http.q = typed;
        loadingSuggestions.value = true;

        try {
            const response = await http.get(DrugSuggestionController.url());

            if (text.value.trim() === typed) {
                suggestions.value = response.data;
            }
        } catch {
            // Suggestions are a convenience: a failed request simply shows none.
            suggestions.value = [];
        } finally {
            loadingSuggestions.value = false;
        }
    }

    watch(text, (value) => {
        clearTimeout(timer);
        const typed = value.trim();

        if (typed.length < minLength() || typed === lastQuery.value) {
            suggestions.value = [];

            return;
        }

        timer = setTimeout(() => void loadSuggestions(typed), SUGGEST_DELAY_MS);
    });

    function search(query: string = text.value): void {
        const q = query.trim();

        if (q.length < minLength()) {
            return;
        }

        text.value = q;
        lastQuery.value = q;
        clearSuggestions();

        router.visit(home({ query: { q } }), {
            preserveState: true,
            onStart: () => {
                searching.value = true;
                failed.value = false;
            },
            onFinish: () => {
                searching.value = false;
            },
            onHttpException: () => {
                failed.value = true;
                notify.error(t('search.error'));

                return false;
            },
            onNetworkError: () => {
                failed.value = true;
                notify.error(t('search.error'));

                return false;
            },
        });
    }

    function retry(): void {
        search(lastQuery.value || text.value);
    }

    onBeforeUnmount(clearSuggestions);

    return {
        text,
        suggestions,
        loadingSuggestions,
        searching,
        failed,
        tooShort,
        search,
        retry,
        clearSuggestions,
    };
}
