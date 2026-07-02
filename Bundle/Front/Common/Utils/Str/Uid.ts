let counter = 0;

export const uid = (prefix: string = ''): string => {
    counter++;
    const timestamp = Date.now().toString(36);
    const random = Math.random().toString(36).substring(2, 9);
    const count = counter.toString(36);

    return (prefix === '' ? '' : prefix + '-') + timestamp + '-' + count + '-' + random;
};
