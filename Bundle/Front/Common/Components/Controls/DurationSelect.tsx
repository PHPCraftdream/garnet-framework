import * as React from 'react';

const DURATION_OPTIONS = [30, 45, 60, 90, 120];

interface Props {
    value: number;
    onChange: (value: number) => void;
    className?: string;
    name?: string;
    id?: string;
    'data-test-id'?: string;
}

export const DurationSelect: React.FC<Props> = ({value, onChange, className, name, id, ...rest}) => {
    return (
        <select
            className={className || 'form-select'}
            value={value}
            onChange={e => onChange(parseInt(e.target.value))}
            name={name}
            id={id}
            data-test-id={rest['data-test-id']}
        >
            {DURATION_OPTIONS.map(opt => (
                <option key={opt} value={opt}>{opt}</option>
            ))}
        </select>
    );
};
