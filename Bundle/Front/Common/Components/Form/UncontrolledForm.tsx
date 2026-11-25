import * as React from 'react';
import {AntiHookComponent} from '@react-carburetor/core';
import {FormErrorContext} from './internal/FormErrorContext';
import {FormErrorStore} from './internal/FormErrorStore';

interface FormProps {
    onSubmit: (values: Record<string, string>, form: HTMLFormElement) => Promise<void> | void;
    'data-test-id'?: string;
    children: React.ReactNode;
}

/** Imperative operations exposed by an uncontrolled form ref. */
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
        return <FormView {...props} ref={ref as React.Ref<FormView>} />;
    },
);

class FormView extends AntiHookComponent<FormProps> implements UncontrolledFormHandle {
    private readonly errors = new FormErrorStore();

    /** Sets the current field errors. */
    public readonly setFieldErrors = (errors: Record<string, string>): void => {
        this.errors.setErrors(errors);
    };

    /** Returns the current field errors. */
    public readonly getFieldErrors = (): Record<string, string> => this.errors.getErrors();

    /** Reads native form values and invokes the submit callback. */
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

    /** Renders the form and provides its field-error store. */
    public render() {
        return (
            <FormErrorContext.Provider value={this.errors}>
                <form
                    onSubmit={this.handleSubmit}
                    data-test-id={this.props['data-test-id']}
                >
                    {this.props.children}
                </form>
            </FormErrorContext.Provider>
        );
    }
}
