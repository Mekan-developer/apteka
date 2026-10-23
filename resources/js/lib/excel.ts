/**
 * Excel column name for a zero-based index: 0 → A, 25 → Z, 26 → AA.
 */
export function columnName(index: number): string {
    let name = '';

    for (
        let number = index + 1;
        number > 0;
        number = Math.floor((number - 1) / 26)
    ) {
        name = String.fromCharCode(65 + ((number - 1) % 26)) + name;
    }

    return name;
}
