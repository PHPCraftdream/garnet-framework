import * as React from 'react';
import type {IDataListItem} from '@common/Support/Models';
import {useFormErrors} from '@common/Components/Form/useFormErrors';
import {FormBuilderError} from './FormBuilderError';

interface Props {
    name: string;
    defaultValue?: string;
    options: IDataListItem[];
    disabled?: boolean;
    testId?: string;
}

const renderOption = (item: IDataListItem) => <option key={item.value} value={item.value}>{item.text}</option>;

/** Uncontrolled native select for FormBuilder. */
export const FormBuilderSelect = React.forwardRef<HTMLSelectElement, Props>(function FormBuilderSelect(props, ref) {
    const error = useFormErrors(props.name) as string;
    return (
        <>
            <select
                ref={ref}
                name={props.name}
                defaultValue={props.defaultValue ?? ''}
                disabled={props.disabled}
                className="form-datalist-select"
                data-test-id={props.testId}
                aria-invalid={error ? 'true' : undefined}
            >
                {props.options.map(renderOption)}
            </select>
            {error && <FormBuilderError error={error} testId={props.testId} />}
        </>
    );
});
