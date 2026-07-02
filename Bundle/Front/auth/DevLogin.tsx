import * as React from 'react';
import {sendPost} from '@common/Api/sendPost';
import {useConfirm} from '@common/hooks/useConfirm';
import {ConfirmModal} from '@common/Components/ConfirmModal';
import {I18nFramework as I18n} from '@framework/I18nGen/I18nFramework';

const ROLES = ['admin', 'owner', 'moderator', 'expert', 'user'] as const;

interface Props {
    floating?: boolean;
}

interface DevLoginResponse {
    success?: boolean;
    redirect?: string;
}

export const DevLoginButtons: React.FC<Props> = ({floating = false}) => {
    const [loading, setLoading] = React.useState<string | null>(null);
    const [resetting, setResetting] = React.useState(false);
    const {confirmState, confirm, handleConfirm, handleCancel} = useConfirm();

    const busy = loading !== null || resetting;

    const handleDevLogin = async (role: string): Promise<void> => {
        setLoading(role);
        try {
            const data = await sendPost<{role: string}, DevLoginResponse>('/dev-login', {role});
            if (data.success) {
                window.location.href = data.redirect || '/';
            } else {
                setLoading(null);
            }
        } catch {
            setLoading(null);
        }
    };

    const handleReset = async (): Promise<void> => {
        const ok = await confirm('Reset the database? All data and dev accounts will be deleted.', {
            variant: 'danger',
            confirmLabel: I18n.Common_Delete?.() || 'Reset',
        });
        if (!ok) return;
        setResetting(true);
        try {
            await sendPost<{}, {}>('/dev-login/reset-db', {});
            // TODO: tech debt — full page reload after dev DB reset. Architecturally we'd need
            // to re-fetch every island's state + invalidate session/CSRF/lang caches, which is
            // out of scope for a dev-only helper. Reload is the pragmatic path until DevLogin
            // is rewritten to drive a global app-state refresh.
            window.location.reload();
        } catch {
            setResetting(false);
        }
    };

    const wrapStyle: React.CSSProperties = floating
        ? {
            position: 'fixed',
            bottom: 0,
            left: 0,
            right: 0,
            zIndex: 50,
            background: 'var(--color-bg-surface, rgba(255,255,255,0.92))',
            borderTop: '1px dashed #d1d5db',
            padding: '6px 16px',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            gap: '8px',
            flexWrap: 'wrap',
            backdropFilter: 'blur(4px)',
        }
        : {
            marginTop: '24px',
            borderTop: '1px dashed #d1d5db',
            paddingTop: '16px',
        };

    const label = (
        <span style={{fontSize: '11px', color: '#9ca3af', marginRight: '4px', whiteSpace: 'nowrap'}}>
            Dev:
        </span>
    );

    const buttons = (
        <>
            {ROLES.map(role => (
                <button
                    key={role}
                    type="button"
                    disabled={busy}
                    onClick={() => handleDevLogin(role)}
                    data-test-id={`dev-login-${role}`}
                    style={{
                        padding: '2px 10px',
                        fontSize: '12px',
                        border: '1px dashed #d1d5db',
                        color: loading === role ? '#374151' : '#9ca3af',
                        borderRadius: '4px',
                        background: 'transparent',
                        cursor: busy ? 'default' : 'pointer',
                    }}
                >
                    {loading === role ? '...' : role}
                </button>
            ))}
            <button
                type="button"
                disabled={busy}
                onClick={handleReset}
                data-test-id="dev-reset-db"
                style={{
                    padding: '2px 10px',
                    fontSize: '12px',
                    border: '1px dashed #fca5a5',
                    color: resetting ? '#374151' : '#f87171',
                    borderRadius: '4px',
                    background: 'transparent',
                    cursor: busy ? 'default' : 'pointer',
                    marginLeft: '8px',
                }}
            >
                {resetting ? '...' : 'reset db'}
            </button>
        </>
    );

    const content = floating
        ? <div style={wrapStyle}>{label}{buttons}</div>
        : (
            <div style={wrapStyle}>
                <p style={{fontSize: '12px', color: '#9ca3af', marginBottom: '8px'}}>Dev quick login:</p>
                <div style={{display: 'flex', flexWrap: 'wrap', gap: '8px', alignItems: 'center'}}>
                    {buttons}
                </div>
            </div>
        );

    return (
        <>
            {content}
            <ConfirmModal state={confirmState} onConfirm={handleConfirm} onCancel={handleCancel} />
        </>
    );
};
