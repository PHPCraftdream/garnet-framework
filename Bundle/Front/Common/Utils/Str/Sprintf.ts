export const sprintf = (format: string, args: (string | number)[]): string => {
    let index = 0;
    return format.replace(/%[sd]/g, (match: string): string => {
        const arg = args[index++];

        if (match === '%s') {
            return String(arg);
        } else if (match === '%d') {
            return Number(arg).toString();
        }

        return match;
    });
};
