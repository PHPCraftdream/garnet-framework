import * as React from 'react';
import {useFormErrors} from './useFormErrors';

interface Props {
    name: string;
    defaultValue?: string;
    rows?: number;
    cols?: number;
    placeholder?: string;
    disabled?: boolean;
    required?: boolean;
    autoFocus?: boolean;
    className?: string;
    errorClassName?: string;
    testId?: string;
    onBlur?: (value: string, el: HTMLTextAreaElement) => void;
}

export const UncontrolledTextarea = React.forwardRef<HTMLTextAreaElement, Props>(
    function UncontrolledTextarea(p, ref) {
        const error = useFormErrors(p.name) as string;
        return (
            <>
                <textarea
                    ref={ref}
                    name={p.name}
                    defaultValue={p.defaultValue ?? ''}
                    rows={p.rows ?? 7}
                    cols={p.cols}
                    placeholder={p.placeholder}
                    disabled={p.disabled}
                    required={p.required}
                    autoFocus={p.autoFocus}
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
