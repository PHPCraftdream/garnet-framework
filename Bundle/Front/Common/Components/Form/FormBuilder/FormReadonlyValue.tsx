import * as React from 'react';

interface Props {
    value: string;
}

/** Read-only field display used by FormBuilder. */
export function FormReadonlyValue({value}: Props) {
    return <div className="form-readonly-value">{value}</div>;
}
