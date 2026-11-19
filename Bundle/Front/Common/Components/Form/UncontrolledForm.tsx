import * as React from 'react';
import {AntiHookComponent} from '@react-carburetor/core';
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
        const viewRef = React.useRef<FormView>(null);
        React.useImperativeHandle(ref, () => viewRef.current!, []);
        return <FormView {...props} ref={viewRef} />;
    },
);

class FormView extends AntiHookComponent<FormProps> implements UncontrolledFormHandle {
    private readonly errors = new FormErrorStore();

    public readonly setFieldErrors = (errors: Record<string, string>): void => {
        this.errors.setErrors(errors);
    };

    public readonly getFieldErrors = (): Record<string, string> => this.errors.getErrors();

    private readonly handleSubmit = (e: React.FormEvent<HTMLFormElement>): void => {
        e.preventDefault();
        const form = e.currentTarget;
        const fd = new FormData(form);
        const values: Record<string, string> = {};
        fd.forEach((v, k) => {
            values[k] = typeof v === 'string' ? v : '';
        });
        this.props.onSubmit(values, form);
    };

    public render() {
        return (
            <FormErrorProvider value={this.errors}>
                <form
                    onSubmit={this.handleSubmit}
                    // carburetor-disable-next-line require-function-class
                    className={this.props.className}
                    data-test-id={this.props['data-test-id']}
                >
                    {this.props.children}
                </form>
            </FormErrorProvider>
        );
    }
}
