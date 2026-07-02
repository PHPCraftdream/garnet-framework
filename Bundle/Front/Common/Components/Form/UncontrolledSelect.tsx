import * as React from 'react';
import {useFormErrors} from './useFormErrors';
import type {IDataListItem} from '@common/Models';

interface Props {
    name: string;
    defaultValue?: string;
    options: IDataListItem[];
    disabled?: boolean;
    required?: boolean;
    className?: string;
    errorClassName?: string;
    testId?: string;
    onBlur?: (value: string, el: HTMLSelectElement) => void;
}

export const UncontrolledSelect = React.forwardRef<HTMLSelectElement, Props>(
    function UncontrolledSelect(p, ref) {
        const error = useFormErrors(p.name) as string;
        return (
            <>
                <select
                    ref={ref}
                    name={p.name}
                    defaultValue={p.defaultValue ?? ''}
                    disabled={p.disabled}
                    required={p.required}
                    className={p.className ?? 'form-select'}
                    data-test-id={p.testId}
                    onBlur={p.onBlur ? (e) => p.onBlur!(e.currentTarget.value, e.currentTarget) : undefined}
                    aria-invalid={error ? 'true' : undefined}
                >
                    {p.options.map((item) => (
                        <option key={item.value} value={item.value}>
                            {item.text}
                        </option>
                    ))}
                </select>
                {error && (
                    <div className={p.errorClassName ?? 'garnet-form-error small fs-7 text-danger'} data-test-id={p.testId ? `${p.testId}-error` : undefined}>
                        {error}
                    </div>
                )}
            </>
        );
    },
);
