import * as React from 'react';
import {useFormErrors} from './useFormErrors';

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
    className?: string;
    errorClassName?: string;
    testId?: string;
    onBlur?: (value: string, el: HTMLInputElement) => void;
}

export const UncontrolledInput = React.forwardRef<HTMLInputElement, Props>(
    function UncontrolledInput(p, ref) {
        const error = useFormErrors(p.name) as string;
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
                    className={p.className ?? 'form-control'}
                    data-test-id={p.testId}
                    onBlur={p.onBlur ? (e) => p.onBlur!(e.currentTarget.value, e.currentTarget) : undefined}
                    aria-invalid={error ? 'true' : undefined}
                />
                {error && (
                    <div className={p.errorClassName ?? 'garnet-form-error small fs-7 text-danger'} data-test-id={p.testId ? `${p.testId}-error` : undefined}>
                        {error}
                    </div>
                )}
            </>
        );
    },
);
