export const awaitThen = <T>(awaitFunc: () => boolean, then: () => T, timeout = 30, count = 10): Promise<T> => {
    return new Promise((resolve, reject) => {
        let i = 0;

        const check = () => {
            if (awaitFunc()) {
                resolve(then());
                return;
            }

            i += 1;

            if (i < count || !count) {
                setTimeout(check, timeout);
            } else {
                reject(new Error(`awaitThen: condition not met after ${count} attempts (${count * timeout}ms)`));
            }
        };

        check();
    })
};
