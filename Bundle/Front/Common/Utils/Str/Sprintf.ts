/**
 * Substitutes `%s` / `%d` placeholders, and collapses `%%` into a literal `%`.
 *
 * The `%%` case is not decoration. Translation strings are shared with the PHP
 * side, whose sprintf treats `%%` as the escape for a literal percent — so a
 * string like `'expert retains %d%% (%d ₽)'` is the correct way to write it
 * once for both. Without the escape here the browser rendered `50%%`, and a
 * naive fix that replaced `%%` in a separate pass would be worse: scanning for
 * `%s`/`%d` first turns `%%d` into a placeholder and silently eats an
 * argument, shifting every value after it.
 *
 * Hence one pass: `%%` is matched by the same regex as the placeholders, and
 * consumes no argument.
 */
export const sprintf = (format: string, args: (string | number)[]): string => {
    let index = 0;
    return format.replace(/%[sd%]/g, (match: string): string => {
        if (match === '%%') {
            return '%';
        }

        const arg = args[index++];

        if (match === '%s') {
            return String(arg);
        } else if (match === '%d') {
            return Number(arg).toString();
        }

        return match;
    });
};
