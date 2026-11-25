import * as React from 'react';
import {AntiHookComponent, Carburetor} from '@react-carburetor/core';
import {sendPost} from '@common/Api/Send/sendPost';
import type {ConfirmState} from '@common/hooks/ui/useConfirm';
import {ConfirmModal} from '@common/Components/Feedback/ConfirmModal';
import {I18nFramework as I18n} from '@framework/I18nGen/I18nFramework';

const DEFAULT_ROLES = ['admin', 'owner', 'moderator', 'user'] as const;

interface Props {
    floating?: boolean;
    roles?: readonly string[];
}

interface DevLoginResponse {
    success?: boolean;
    redirect?: string;
}

type DevLoginState = {loading: string | null; resetting: boolean; confirmState: ConfirmState};

interface DevRoleProps {
    role: string;
    loading: boolean;
    busy: boolean;
    onLogin: (role: string) => void;
}

class DevRoleButton extends React.Component<DevRoleProps> {
    /** Dispatches the role login action. */
    private readonly handleClick = (): void => { this.props.onLogin(this.props.role); };

    /** Renders the role login button. */
    public render() {
        const {role, loading, busy} = this.props;
        return (
            <button type="button" className="dev-login-role" data-loading={loading ? 'true' : undefined}
                disabled={busy} onClick={this.handleClick} data-test-id={`dev-login-${role}`}>
                {loading ? '...' : role}
            </button>
        );
    }
}

export class DevLoginButtons extends AntiHookComponent<Props> {
    private readonly store = new Carburetor<DevLoginState>({
        loading: null,
        resetting: false,
        confirmState: {visible: false, message: '', items: []},
    });
    private confirmResolver: ((confirmed: boolean) => void) | null = null;
    private readonly updateOnParentRender = true;

    /** Updates when props or parent render changes. */
    public shouldComponentUpdate(nextProps: Readonly<Props>, nextState: Readonly<{}>): boolean {
        return super.shouldComponentUpdate(nextProps, nextState) || this.updateOnParentRender;
    }

    /** Starts login for the selected role. */
    private readonly handleRole = (role: string): void => {
        void this.login(role);
    };

    /** Authenticates the selected development role. */
    private async login(role: string): Promise<void> {
        this.store.setData({...this.store.getData(), loading: role});
        try {
            const data = await sendPost<{role: string}, DevLoginResponse>('/dev-login', {role});
            if (data.success) {
                window.location.href = data.redirect || '/';
            } else {
                this.store.setData({...this.store.getData(), loading: null});
            }
        } catch {
            this.store.setData({...this.store.getData(), loading: null});
        }
    }

    /** Requests confirmation before resetting data. */
    private confirmReset(): Promise<boolean> {
        const confirmLabel = I18n.Common_Delete?.() || 'Reset';
        return new Promise(resolve => {
            this.confirmResolver = resolve;
            this.store.setData({
                ...this.store.getData(),
                confirmState: {
                    visible: true,
                    message: 'Reset the database? All data and dev accounts will be deleted.',
                    items: [],
                    variant: 'danger',
                    confirmLabel,
                },
            });
        });
    }

    /** Resolves the pending reset confirmation. */
    private settleConfirm(confirmed: boolean): void {
        const data = this.store.getData();
        this.store.setData({...data, confirmState: {...data.confirmState, visible: false}});
        this.confirmResolver?.(confirmed);
        this.confirmResolver = null;
    }

    /** Confirms the reset dialog. */
    private readonly handleConfirm = (): void => { this.settleConfirm(true); };
    /** Cancels the reset dialog. */
    private readonly handleCancel = (): void => { this.settleConfirm(false); };
    /** Starts the reset flow. */
    private readonly handleReset = (): void => { void this.reset(); };

    /** Resets development data after confirmation. */
    private async reset(): Promise<void> {
        const ok = await this.confirmReset();
        if (!ok) return;
        this.store.setData({...this.store.getData(), resetting: true});
        try {
            await sendPost<{}, {}>('/dev-login/reset-db', {});
            window.location.reload();
        } catch {
            this.store.setData({...this.store.getData(), resetting: false});
        }
    }

    /** Renders login and reset buttons. */
    private renderButtons(roles: readonly string[], loading: string | null, resetting: boolean) {
        const busy = loading !== null || resetting;
        const buttons: React.ReactNode[] = [];
        for (const role of roles) {
            buttons.push(
                <DevRoleButton
                    key={role}
                    role={role}
                    loading={loading === role}
                    busy={busy}
                    onLogin={this.handleRole}
                />
            );
        }
        return (
            <>
                {buttons}
                <button
                    type="button"
                    className="dev-login-reset"
                    data-loading={resetting ? 'true' : undefined}
                    disabled={busy}
                    onClick={this.handleReset}
                    data-test-id="dev-reset-db"
                >
                    {resetting ? '...' : 'reset db'}
                </button>
            </>
        );
    }

    /** Renders the development login controls. */
    public render() {
        const {floating = false, roles = DEFAULT_ROLES} = this.props;
        const {loading, resetting, confirmState} = this.useCarburetor(this.store);
        const modalState: ConfirmState = {
            visible: confirmState.visible,
            message: confirmState.message,
            items: [...confirmState.items],
            confirmLabel: confirmState.confirmLabel,
            variant: confirmState.variant,
        };
        const buttons = this.renderButtons(roles, loading, resetting);
        return (
            <>
                {floating ? (
                    <div className="dev-login-floating">
                        <span className="dev-login-label">Dev:</span>{buttons}
                    </div>
                ) : (
                    <div className="dev-login-panel">
                        <p className="dev-login-heading">Dev quick login:</p>
                        <div className="dev-login-actions">{buttons}</div>
                    </div>
                )}
                <ConfirmModal state={modalState} onConfirm={this.handleConfirm} onCancel={this.handleCancel} />
            </>
        );
    }
}
