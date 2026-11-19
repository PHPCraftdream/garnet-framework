import * as React from 'react';

interface Props {
    personalChecked: boolean;
    marketingChecked: boolean;
    onPersonalChange: React.ChangeEventHandler<HTMLInputElement>;
    onMarketingChange: React.ChangeEventHandler<HTMLInputElement>;
    personalHtml: string;
    marketingLabel: string;
    requiredLabel?: string;
}

/** Controlled consent fields; session and policy decisions remain with Auth2. */
export function AuthConsentFields({
    personalChecked, marketingChecked, onPersonalChange, onMarketingChange,
    personalHtml, marketingLabel, requiredLabel,
}: Props) {
    return (
        <>
            <label className="auth-consent-row">
                <input
                    type="checkbox"
                    checked={personalChecked}
                    onChange={onPersonalChange}
                    data-test-id="auth-consent-pd"
                    className="auth-consent-checkbox"
                />
                <span dangerouslySetInnerHTML={{__html: personalHtml}} />
            </label>
            <label className="auth-consent-row">
                <input
                    type="checkbox"
                    checked={marketingChecked}
                    onChange={onMarketingChange}
                    data-test-id="auth-consent-marketing"
                    className="auth-consent-checkbox"
                />
                <span>{marketingLabel}</span>
            </label>
            {!personalChecked && <div className="auth-consent-required">{requiredLabel}</div>}
        </>
    );
}
