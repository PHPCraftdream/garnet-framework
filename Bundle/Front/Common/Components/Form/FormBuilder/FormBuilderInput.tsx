import * as React from 'react';
import {useFormErrors} from '../useFormErrors';
import {FormBuilderError} from './FormBuilderError';

interface Props {
    name: string;
    defaultValue?: string | number;
    disabled?: boolean;
    testId?: string;
}

/** Uncontrolled native input for FormBuilder. */
export const FormBuilderInput = React.forwardRef<HTMLInputElement, Props>(function FormBuilderInput(props, ref) {
    const error = useFormErrors(props.name) as string;
    return (
        <>
            <input
                ref={ref}
                name={props.name}
                type="text"
                defaultValue={props.defaultValue ?? ''}
                disabled={props.disabled}
                className="form-builder-control"
                data-test-id={props.testId}
                aria-invalid={error ? 'true' : undefined}
            />
            {error && <FormBuilderError error={error} testId={props.testId} />}
        </>
    );
});
