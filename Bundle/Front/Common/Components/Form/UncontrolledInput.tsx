import * as React from 'react';
import {useFormErrors} from './useFormErrors';
import {AntiHookComponent} from '@react-carburetor/core';

interface Props {
    name: string;
    defaultValue?: string | number;
    type?: 'text' | 'email' | 'password' | 'number' | 'tel' | 'url' | 'search' | 'date' | 'time' | 'datetime-local';
    placeholder?: string;
    disabled?: boolean;
    required?: boolean;
    min?: number | string;
    max?: number | string;
    step?: number | string;
    autoFocus?: boolean;
    autoComplete?: string;
    testId?: string;
    onBlur?: (value: string, el: HTMLInputElement) => void;
}

export const UncontrolledInput = React.forwardRef<HTMLInputElement, Props>(
    function UncontrolledInput(p, ref) {
        const error = useFormErrors(p.name) as string;
        return <InputView {...p} inputRef={ref} error={error} />;
    },
);

class InputView extends AntiHookComponent<Props & {inputRef: React.ForwardedRef<HTMLInputElement>; error: string}> {
    /** Handles this component operation. */
    private readonly blur = (event: React.FocusEvent<HTMLInputElement>) => {
        this.props.onBlur?.(event.currentTarget.value, event.currentTarget);
    };

    /** Handles this component operation. */
    public render() {
        const {inputRef: ref, error, ...p} = this.props;
        return (
            <>
                <input
                    ref={ref}
                    name={p.name}
                    type={p.type ?? 'text'}
                    defaultValue={p.defaultValue ?? ''}
                    placeholder={p.placeholder}
                    disabled={p.disabled}
                    required={p.required}
                    min={p.min}
                    max={p.max}
                    step={p.step}
                    autoFocus={p.autoFocus}
                    autoComplete={p.autoComplete}
                    className="uncontrolled-field"
                    data-test-id={p.testId}
                    onBlur={p.onBlur ? this.blur : undefined}
                    aria-invalid={error ? 'true' : undefined}
                />
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
