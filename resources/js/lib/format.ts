/** A price from the server ("2.50") as the visitor's locale writes it: «2,50». */
export function formatPrice(price: string | number, locale: string): string {
    return Number(price).toLocaleString(locale, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

/** An order sum without decimals: «10 000». */
export function formatSum(sum: string | number, locale: string): string {
    return Number(sum).toLocaleString(locale, { maximumFractionDigits: 0 });
}

export function formatDate(date: string, locale: string): string {
    return new Date(date).toLocaleDateString(locale, {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}
