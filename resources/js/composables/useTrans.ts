import { usePage } from '@inertiajs/vue3';

export type TransReplacements = Record<string, string | number>;

export type UseTransReturn = {
    t: (key: string, replacements?: TransReplacements) => string;
    tc: (
        key: string,
        count: number,
        replacements?: TransReplacements,
    ) => string;
    locale: () => string;
};

/**
 * Translate a key from lang/{locale}.json, shared by HandleInertiaRequests.
 * Placeholders use Laravel syntax: t('items.count', { count: 3 }) for ":count".
 * A missing key returns the key itself so it is easy to spot.
 *
 * tc() picks the plural form of the locale: tc('search.variants', 3) reads "search.variants.few"
 * (forms are those of Intl.PluralRules: one, few, many, other), falling back to ".other".
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

    function tc(
        key: string,
        count: number,
        replacements: TransReplacements = {},
    ): string {
        const form = new Intl.PluralRules(page.props.locale).select(count);
        const formKey = page.props.translations?.[`${key}.${form}`]
            ? `${key}.${form}`
            : `${key}.other`;

        return t(formKey, { count, ...replacements });
    }

    return {
        t,
        tc,
        locale: () => page.props.locale,
    };
}
