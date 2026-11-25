import * as React from 'react';
import {useFormErrors} from '@common/Components/Form/useFormErrors';
import {FormBuilderError} from './FormBuilderError';

interface Props {
    name: string;
    defaultValue?: string;
    disabled?: boolean;
    rows?: number;
    testId?: string;
}

/** Uncontrolled native textarea for FormBuilder. */
export const FormBuilderTextarea = React.forwardRef<HTMLTextAreaElement, Props>(
    function FormBuilderTextarea(props, ref) {
    const error = useFormErrors(props.name) as string;
    return (
        <>
            <textarea
                ref={ref}
                name={props.name}
                defaultValue={props.defaultValue ?? ''}
                rows={props.rows ?? 7}
                disabled={props.disabled}
                className="form-builder-control"
                data-test-id={props.testId}
                aria-invalid={error ? 'true' : undefined}
            />
            {error && <FormBuilderError error={error} testId={props.testId} />}
        </>
    );
    },
);
