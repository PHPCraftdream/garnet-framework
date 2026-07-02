import * as React from 'react';
import {useRef, useCallback} from 'react';
import {FormErrorProvider, FormErrorStore} from './useFormErrors';

interface FormProps {
    onSubmit: (values: Record<string, string>, form: HTMLFormElement) => Promise<void> | void;
    className?: string;
    'data-test-id'?: string;
    children: React.ReactNode;
}

export interface UncontrolledFormHandle {
    setFieldErrors: (errors: Record<string, string>) => void;
    getFieldErrors: () => Record<string, string>;
}

/**
 * Form wrapper that provides per-field error context and reads field
 * values from the browser's native FormData on submit.
 *
 * Usage:
 * ```tsx
 * const formRef = useRef<UncontrolledFormHandle>(null);
 *
 * <UncontrolledForm
 *     ref={formRef}
 *     onSubmit={(values) => {
 *         const res = await sendPost(url, values);
 *         if (res.fieldErrors) formRef.current?.setFieldErrors(res.fieldErrors);
 *     }}
 * >
 *     <UncontrolledInput name="email" />
 *     <button type="submit">Save</button>
 * </UncontrolledForm>
 * ```
 */
export const UncontrolledForm = React.forwardRef<UncontrolledFormHandle, FormProps>(
    function UncontrolledForm(props, ref) {
        const storeRef = useRef<FormErrorStore>(new FormErrorStore());

        React.useImperativeHandle(ref, () => ({
            setFieldErrors(errors: Record<string, string>) {
                storeRef.current.setErrors(errors);
            },
            getFieldErrors() {
                return storeRef.current.getErrors();
            },
        }));

        const handleSubmit = useCallback((e: React.FormEvent<HTMLFormElement>) => {
            e.preventDefault();
            const form = e.currentTarget;
            const fd = new FormData(form);
            const values: Record<string, string> = {};
            fd.forEach((v, k) => {
                values[k] = typeof v === 'string' ? v : '';
            });
            props.onSubmit(values, form);
        }, [props.onSubmit]);

        return (
            <FormErrorProvider value={storeRef.current}>
                <form
                    onSubmit={handleSubmit}
                    className={props.className}
                    data-test-id={props['data-test-id']}
                >
                    {props.children}
                </form>
            </FormErrorProvider>
        );
    },
);
