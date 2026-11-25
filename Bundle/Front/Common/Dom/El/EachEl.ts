type FilterNonNullable<T> = T extends [infer H, ...infer R]
    ? [NonNullable<H>, ...FilterNonNullable<R>]
    : [];
type TTupleFunc<T extends any[]> = (...args: T) => void;
type TEachFunc<T extends any[] = unknown[]> = <Args extends T>(...args: Args) =>
    (func: TTupleFunc<FilterNonNullable<Args>>) => void;

/** Builds a callback that runs only when every supplied element exists. */
export const eachEl: TEachFunc = (...args) => {
    for (let item of args) {
        if (item === null || item === undefined) {
            return null;
        }
    }

    return (func) => {
        func(...args as any);
    };
};
