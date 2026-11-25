import {useContext, useSyncExternalStore} from 'react';
import {FormErrorContext} from './internal/FormErrorContext';

type ErrorMap = Record<string, string>;

/** Subscribes to field validation errors, or the full map when no name is given. */
export function useFormErrors(name?: string): string | ErrorMap {
    const store = useContext(FormErrorContext);
    if (!store) return name ? '' : {};

    if (name === undefined) {
        return useSyncExternalStore(
            (cb) => store.subscribeAny(cb),
            () => store.getErrors(),
        );
    }

    return useSyncExternalStore(
        (cb) => store.subscribe(name, cb),
        () => store.getError(name),
    );
}
