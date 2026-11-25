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

/** Renders the send action with its pending state and selected visual variant. */
export default function SendButton({
    onClick, disabled, sending, label, testId, variant = 'primary', size = 'md',
}: Props) {
    return (
        <button
            type="button"
            className={variant === 'outline-warning'
                ? (size === 'sm' ? 'send-button-warning-small' : 'send-button-warning')
                : (size === 'sm' ? 'send-button-primary-small' : 'send-button-primary')}
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
