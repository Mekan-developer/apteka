import { usePage } from '@inertiajs/vue3';

export type TransReplacements = Record<string, string | number>;

export type UseTransReturn = {
    t: (key: string, replacements?: TransReplacements) => string;
    locale: () => string;
};

/**
 * Translate a key from lang/{locale}.json, shared by HandleInertiaRequests.
 * Placeholders use Laravel syntax: t('items.count', { count: 3 }) for ":count".
 * A missing key returns the key itself so it is easy to spot.
 */
export function useTrans(): UseTransReturn {
    const page = usePage();

    function t(key: string, replacements: TransReplacements = {}): string {
        const line = page.props.translations?.[key] ?? key;

        return Object.entries(replacements).reduce(
            (text, [name, value]) => text.replaceAll(`:${name}`, String(value)),
            line,
        );
    }

    return {
        t,
        locale: () => page.props.locale,
    };
}
