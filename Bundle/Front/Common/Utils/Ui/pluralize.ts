/**
 * Russian-aware pluralization (3-form: 1 slot, 2 slots, 5 slots).
 * English works correctly with form1/form2 (form5 = form2 in EN data).
 *
 * @param n     — the count
 * @param form1 — singular form (1 slot)
 * @param form2 — few form     (2 slots)
 * @param form5 — many form    (5 slots)
 * @returns     — e.g. "3 slots"
 */
export function pluralize(n: number, form1: string, form2: string, form5: string): string {
    const abs = Math.abs(n);
    const mod10 = abs % 10;
    const mod100 = abs % 100;

    let form: string;
    if (mod10 === 1 && mod100 !== 11) {
        form = form1;
    } else if (mod10 >= 2 && mod10 <= 4 && (mod100 < 10 || mod100 >= 20)) {
        form = form2;
    } else {
        form = form5;
    }

    return `${n} ${form}`;
}
