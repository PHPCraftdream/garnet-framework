import * as React from 'react';
import {AntiHookComponent} from '@react-carburetor/core';
import {useFormErrors} from './useFormErrors';
import type {IDataListItem} from '@common/Support/Models';

interface Props {
    name: string;
    defaultValue?: string;
    options: IDataListItem[];
    disabled?: boolean;
    required?: boolean;
    testId?: string;
    onBlur?: (value: string, el: HTMLSelectElement) => void;
}

export const UncontrolledSelect = React.forwardRef<HTMLSelectElement, Props>(
    function UncontrolledSelect(p, ref) {
        const error = useFormErrors(p.name) as string;
        return <SelectView {...p} inputRef={ref} error={error} />;
    },
);

/** Handles this operation. */
function renderOption(item: IDataListItem) {
    return <option key={item.value} value={item.value}>{item.text}</option>;
}

class SelectView extends AntiHookComponent<Props & {inputRef: React.ForwardedRef<HTMLSelectElement>; error: string}> {
    /** Handles this component operation. */
    private readonly blur = (event: React.FocusEvent<HTMLSelectElement>) => {
        this.props.onBlur?.(event.currentTarget.value, event.currentTarget);
    };

    /** Handles this component operation. */
    public render() {
        const {inputRef, error, ...p} = this.props;
        return (
            <>
                <select
                    ref={inputRef}
                    name={p.name}
                    defaultValue={p.defaultValue ?? ''}
                    disabled={p.disabled}
                    required={p.required}
                    className="uncontrolled-select"
                    data-test-id={p.testId}
                    onBlur={p.onBlur ? this.blur : undefined}
                    aria-invalid={error ? 'true' : undefined}
                >
                    {p.options.map(renderOption)}
                </select>
                {error && (
                    <div className="garnet-form-error" data-uncontrolled-error="true" data-test-id={p.testId
    ? `${p.testId}-error` : undefined}>
                        {error}
                    </div>
                )}
            </>
        );
    }
}
