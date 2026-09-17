export const resolveTimeout = <T>(timeout: number, value: T = undefined): Promise<T> => {
    return new Promise((resolve) => {
        setTimeout(() => resolve(value), timeout);
    })
};
