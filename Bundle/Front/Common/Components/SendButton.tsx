import * as React from 'react';

interface Props {
    onClick: () => void;
    disabled?: boolean;
    sending: boolean;
    label: string;
    testId: string;
    variant?: 'primary' | 'outline-warning';
    size?: 'sm' | 'md';
}

export default function SendButton({onClick, disabled, sending, label, testId, variant = 'primary', size = 'md'}: Props) {
    const btnClass = variant === 'outline-warning'
        ? `btn btn-outline-warning${size === 'sm' ? ' btn-sm' : ''}`
        : `btn btn-primary${size === 'sm' ? ' btn-sm' : ''}`;

    return (
        <button
            type="button"
            className={btnClass}
            disabled={disabled || sending}
            aria-busy={sending}
            onClick={onClick}
            data-test-id={testId}
        >
            {sending ? (
                <span className="common-send-spinner-wrap">
                    <span className="common-spinner" aria-hidden="true" />
                    {label}
                </span>
            ) : label}
        </button>
    );
}
