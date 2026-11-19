import * as React from 'react';
import {AntiHookComponent} from '@react-carburetor/core';
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
        return <TextareaView {...p} inputRef={ref} error={error} />;
    },
);

class TextareaView extends AntiHookComponent<Props & {inputRef: React.ForwardedRef<HTMLTextAreaElement>; error: string}> {
    private readonly blur = (event: React.FocusEvent<HTMLTextAreaElement>) => {
        this.props.onBlur?.(event.currentTarget.value, event.currentTarget);
    };

    public render() {
        const {inputRef, error, ...p} = this.props;
        return (
            <>
                <textarea
                    ref={inputRef}
                    name={p.name}
                    defaultValue={p.defaultValue ?? ''}
                    rows={p.rows ?? 7}
                    cols={p.cols}
                    placeholder={p.placeholder}
                    disabled={p.disabled}
                    required={p.required}
                    autoFocus={p.autoFocus}
                    // carburetor-disable-next-line require-function-class
                    className={p.className ?? 'uncontrolled-field'}
                    data-test-id={p.testId}
                    onBlur={p.onBlur ? this.blur : undefined}
                    aria-invalid={error ? 'true' : undefined}
                />
                {error && (
                    // carburetor-disable-next-line require-function-class
                    <div className={p.errorClassName ?? 'garnet-form-error'} data-uncontrolled-error={p.errorClassName == null ? true : undefined} data-test-id={p.testId ? `${p.testId}-error` : undefined}>
                        {error}
                    </div>
                )}
            </>
        );
    }
}
