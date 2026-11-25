import * as React from 'react';
import {AntiHookComponent, Carburetor} from '@react-carburetor/core';
import {EPhase} from '@framework/auth/Enums';
import {I18nFramework as I18n} from '@framework/I18nGen/I18nFramework';
import {IAuthData, TInputRef, TSetAuthState} from '@framework/auth/Models';
import {getInputPlaceholder} from '@framework/auth/utils/GetInputPlaceholder';
import {getHintParams} from '@framework/auth/utils/GetHintParams';
import {getButtonParams} from '@framework/auth/utils/GetButtonParams';
import {CodeTimer} from '@framework/auth/utils/CodeTimer';
import {AuthConsentFields} from '@framework/auth/AuthConsentFields';
import {renderMarkdownLinks} from '@common/Utils/Url/renderMarkdownLinks';
import {getInputParams} from './utils/GetInputParams';
import {handleRequestCode} from '@framework/auth/utils/HandleRequestCode';
import {handleCheckCode} from '@framework/auth/utils/HandleCheckCode';
import {startSession} from '@common/Api/Send/startSession';
import {IGarnetWindow} from '@common/Support/Models';

const w: IGarnetWindow = window as IGarnetWindow;

type AuthRuntime = {auth: IAuthData; pdConsent: boolean; mkConsent: boolean; csrfReady: boolean};

const isEmailPhase = (phase: EPhase): boolean => [
    EPhase.INPUT_EMAIL,
    EPhase.INPUT_EMAIL_AFTER_TIMEOUT,
    EPhase.INPUT_EMAIL_AFTER_FAIL_TRIES,
    EPhase.INPUT_EMAIL_WRONG_VALUE,
    EPhase.INPUT_EMAIL_REQUEST_ERROR,
].includes(phase);

export class Auth2Island extends AntiHookComponent<Partial<IAuthData>> {
    private readonly inputRef = React.createRef<HTMLInputElement>() as TInputRef;
    private readonly store = new Carburetor<AuthRuntime>({
        auth: {phase: EPhase.INPUT_EMAIL, ...this.props},
        pdConsent: false,
        mkConsent: false,
        csrfReady: !!w.__GARNET_CSRF__,
    });
    private mounted = true;
    private timeoutPhase = this.store.getData().auth.phase;
    private timeoutHandler = this.makeTimeoutHandler(this.store.getData().auth);

    /** Marks the component as mounted. */
    public componentDidMount(): void {
        super.componentDidMount();
        this.mounted = true;
    }

    /** Marks the component as unmounted. */
    public componentWillUnmount(): void {
        this.mounted = false;
        super.componentWillUnmount();
    }

/** Updates authentication state while mounted. */
    private readonly setData: TSetAuthState = update => {
        if (!this.mounted) return;
        const current = this.store.getData();
        const auth = typeof update === 'function' ? update(current.auth) : update;
        if (auth.phase !== this.timeoutPhase) {
            this.timeoutPhase = auth.phase;
            this.timeoutHandler = this.makeTimeoutHandler(auth);
        }
        this.store.setData({...current, auth});
    };

    /** Creates a timeout handler for the authentication phase. */
    private makeTimeoutHandler(snapshot: IAuthData): () => void {
        return () => this.setData({...snapshot, phase: EPhase.INPUT_EMAIL_AFTER_TIMEOUT});
    }

    /** Updates consent state while mounted. */
    private setConsent(patch: Partial<Pick<AuthRuntime, 'pdConsent' | 'mkConsent' | 'csrfReady'>>): void {
        if (this.mounted) this.store.setData({...this.store.getData(), ...patch});
    }

    /** Dispatches the action for the current phase. */
    private buttonClick(phase: EPhase): void {
        switch (phase) {
            case EPhase.INPUT_EMAIL:
            case EPhase.INPUT_EMAIL_AFTER_TIMEOUT:
            case EPhase.INPUT_EMAIL_AFTER_FAIL_TRIES:
            case EPhase.INPUT_EMAIL_WRONG_VALUE:
            case EPhase.INPUT_EMAIL_REQUEST_ERROR:
                handleRequestCode(this.setData, this.inputRef);
                return;
            case EPhase.INPUT_CODE:
            case EPhase.INPUT_CODE_FAIL:
            case EPhase.INPUT_CODE_WRONG_VALUE:
            case EPhase.INPUT_CODE_REQUEST_ERROR:
                handleCheckCode(this.setData, this.inputRef);
                return;
            default:
                const _: never = null as never;
        }
    }

    /** Updates personal-data consent. */
    private readonly changePersonal = async (event: React.ChangeEvent<HTMLInputElement>): Promise<void> => {
        const checked = event.currentTarget.checked;
        const {csrfReady, mkConsent} = this.store.getData();
        this.setConsent({pdConsent: checked});
        if (checked && !csrfReady) {
            try {
                await startSession(mkConsent);
                this.setConsent({csrfReady: true});
            } catch {
                this.setConsent({pdConsent: false, csrfReady: false});
            }
        }
    };

    /** Updates marketing consent. */
    private readonly changeMarketing = (event: React.ChangeEvent<HTMLInputElement>): void => {
        this.setConsent({mkConsent: event.currentTarget.checked});
    };

    /** Handles authentication form submission. */
    private readonly onSubmit = (event: React.FormEvent<HTMLFormElement>): void => {
        event.preventDefault();
        const {auth, pdConsent, csrfReady} = this.store.getData();
        if (!auth.isSendingRequest && !(isEmailPhase(auth.phase) && (!pdConsent || !csrfReady))) {
            this.buttonClick(auth.phase);
        }
    };

    /** Renders the authentication island. */
    public render() {
        const {auth, pdConsent, mkConsent, csrfReady} = this.useCarburetor(this.store);
        return this.renderForm({...auth}, pdConsent, mkConsent, csrfReady);
    }

    /** Renders the authentication form for the current state. */
    private renderForm(state: IAuthData, pdConsent: boolean, mkConsent: boolean, csrfReady: boolean) {
        if (!state?.phase) {
            return (
                <div className="auth2-shell">
                    <div className="auth2-heading">
                        <h1 className="auth2-title">{I18n.Common_UnknownRequestError()}</h1>
                    </div>
                </div>
            );
        }

        const phase = state.phase;
        const placeholder = getInputPlaceholder(phase);
        const inputParams = getInputParams(state);
        const hint = getHintParams(state);
        const button = getButtonParams(state);
        const emailPhase = isEmailPhase(phase);
        const disabled = !!state.isSendingRequest || (emailPhase && (!pdConsent || !csrfReady));

        return (
            <div className="auth2-shell">
                <div className="auth2-heading">
                    <h1 className="auth2-title" data-test-id="auth-title">{state.title || I18n.Auth()}</h1>
                    <CodeTimer value={state.codeLifeTime} onTimeout={this.timeoutHandler} />
                </div>
                {state.isSendingRequest && (
                    <div className="auth2-loading" data-test-id="auth-loading">
                        <span className="auth2-spinner" aria-hidden="true" />
                        <span>{emailPhase ? I18n.Auth_SendingCode() : I18n.Auth_Verifying()}</span>
                    </div>
                )}
                <form
                    className="auth2-form" hidden={!!state.isSendingRequest}
                    autoComplete="on"
                    onSubmit={this.onSubmit}
                >
                    <div className="auth2-field">
                        <label className="auth2-label">{placeholder}</label>
                        <input
                            type={inputParams.type}
                            name={inputParams.name}
                            placeholder={placeholder}
                            className="auth2-input"
                            autoComplete={inputParams.autoComplete}
                            required
                            ref={this.inputRef}
                            data-test-id={emailPhase ? 'auth-login-input' : 'auth-code-input'}
                        />
                    </div>
                    <div
                        className="auth2-hint"
                        data-tone={phase === EPhase.INPUT_EMAIL || phase === EPhase.INPUT_CODE ? undefined : 'danger'}
                    >{hint?.hint}</div>
                    {emailPhase && (
                        <AuthConsentFields
                            personalChecked={pdConsent}
                            marketingChecked={mkConsent}
                            onPersonalChange={this.changePersonal}
                            onMarketingChange={this.changeMarketing}
                            personalHtml={renderMarkdownLinks(I18n.Consent_PD())}
                            marketingLabel={I18n.Consent_Marketing()}
                            requiredLabel={!pdConsent ? I18n.Consent_PD_Required() : undefined}
                        />
                    )}
                    <div className="auth2-submit-wrap">
                        <button
                            type="submit"
                            className={emailPhase ? 'auth2-submit-primary' : 'auth2-submit-success'}
                            disabled={disabled}
                            data-test-id="auth-submit-btn"
                        >
                            {button?.title}
                        </button>
                    </div>
                </form>
            </div>
        );
    }
}
