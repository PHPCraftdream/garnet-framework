import * as React from 'react';

interface Props {
    error: string;
    testId?: string;
}

/** Field error with the selector used by FormTool's scroll-to-error path. */
export function FormBuilderError({error, testId}: Props) {
    return (
        <div
            className="garnet-form-error"
            data-form-row-error="true"
            data-test-id={testId ? `${testId}-error` : undefined}
        >
            {error}
        </div>
    );
}
