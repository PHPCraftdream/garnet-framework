import * as React from 'react';
import {useCallback, useEffect, useRef, useState} from 'react';
import {EPhase} from '@framework/auth/Enums';
import {I18nFramework as I18n} from '@framework/I18nGen/I18nFramework';
import {IAuthData, TInputRef} from '@framework/auth/Models';
import {getInputPlaceholder} from '@framework/auth/utils/GetInputPlaceholder';
import {getHintParams} from '@framework/auth/utils/GetHintParams';
import {getButtonParams} from '@framework/auth/utils/GetButtonParams';
import {CodeTimer} from '@framework/auth/utils/CodeTimer';
import {renderMarkdownLinks} from '@common/Utils/staticPageUrl';
import {getInputParams} from './utils/GetInputParams';
import {handleRequestCode} from '@framework/auth/utils/HandleRequestCode';
import {handleCheckCode} from '@framework/auth/utils/HandleCheckCode';
import {startSession} from '@common/Api/startSession';
import {IGarnetWindow} from '@common/Models';

const w: IGarnetWindow = window as IGarnetWindow;

export const getCodeFromHash = (): string => {
    return window.location.hash.match(/token=([A-Z0-9]{8,})/i)?.[1] ?? '';
};

const cleanHash = () => {
    try {
        history.replaceState(
            null, '',
            window.location.pathname + window.location.search,
        );
    } catch {
        window.location.hash = '';
    }
};

export const Auth2Island: React.FC<Partial<IAuthData>> = (props) => {
    const [state, setData] = useState<IAuthData>({phase: EPhase.INPUT_EMAIL, ...props});
    const inputRef: TInputRef = useRef<HTMLInputElement>(null);

    const [pdConsent, setPdConsent] = useState(false);
    const [mkConsent, setMkConsent] = useState(false);
    const [csrfReady, setCsrfReady] = useState<boolean>(!!(w as any).__GARNET_CSRF__);

    const renderConsentPd = (): string => renderMarkdownLinks(I18n.Consent_PD());

    const [pendingHashCode, setPendingHashCode] = useState<string>(() => getCodeFromHash());

    // Magic-link auto-verify: a code captured from the email link's #token is
    // submitted automatically once the session is in a code-entry phase. In the
    // email phases it's held in state until the code is actually requested.
    useEffect(() => {
        if (!pendingHashCode) {
            return;
        }

        switch (state?.phase) {
            case EPhase.INPUT_EMAIL:
            case EPhase.INPUT_EMAIL_AFTER_TIMEOUT:
            case EPhase.INPUT_EMAIL_AFTER_FAIL_TRIES:
            case EPhase.INPUT_EMAIL_WRONG_VALUE:
            case EPhase.INPUT_EMAIL_REQUEST_ERROR: {
                return;
            }
            case EPhase.INPUT_CODE:
            case EPhase.INPUT_CODE_FAIL:
            case EPhase.INPUT_CODE_WRONG_VALUE:
            case EPhase.INPUT_CODE_REQUEST_ERROR: {
                if (inputRef.current) {
                    inputRef.current.value = pendingHashCode;
                }
                setPendingHashCode('');
                cleanHash();
                setTimeout(() => {
                    handleCheckCode(setData, inputRef);
                });
                return;
            }
            default:
                const _: never = null as never;
        }
    }, [state?.phase, pendingHashCode]);

    /**
     * Button click handler.
     */
    const buttonClick = useCallback(() => {
        switch (state?.phase) {
            case EPhase.INPUT_EMAIL:
            case EPhase.INPUT_EMAIL_AFTER_TIMEOUT:
            case EPhase.INPUT_EMAIL_AFTER_FAIL_TRIES:
            case EPhase.INPUT_EMAIL_WRONG_VALUE:
            case EPhase.INPUT_EMAIL_REQUEST_ERROR: {
                handleRequestCode(setData, inputRef);

                return;
            }
            case EPhase.INPUT_CODE:
            case EPhase.INPUT_CODE_FAIL:
            case EPhase.INPUT_CODE_WRONG_VALUE:
            case EPhase.INPUT_CODE_REQUEST_ERROR: {
                handleCheckCode(setData, inputRef);
                return;
            }
            default:
                const _: never = null as never;
        }
    }, [state?.phase]);

    const onTimeout = useCallback(() => {
        setData({...state, phase: EPhase.INPUT_EMAIL_AFTER_TIMEOUT});
    }, [state?.phase]);

    if (!state?.phase) {
        return (
            <div className="w-full m-auto" style={{maxWidth: '500px'}}>
                <div className="flex mb-3">
                    <h1 className="text-lg font-normal grow auth-title">{I18n.Common_UnknownRequestError()}</h1>
                </div>
            </div>
        );
    }

    const placeholder = getInputPlaceholder(state.phase);
    const inputParams = getInputParams(state);
    const hint = getHintParams(state);
    const button = getButtonParams(state);
    const disabled = !!state?.isSendingRequest || !pdConsent || !csrfReady;
    const isEmailPhase = [
        EPhase.INPUT_EMAIL,
        EPhase.INPUT_EMAIL_AFTER_TIMEOUT,
        EPhase.INPUT_EMAIL_AFTER_FAIL_TRIES,
        EPhase.INPUT_EMAIL_WRONG_VALUE,
        EPhase.INPUT_EMAIL_REQUEST_ERROR,
    ].includes(state?.phase);

    return (
        <div className="w-full m-auto" style={{maxWidth: '500px'}}>
            <div className="flex mb-3">
                <h1 className="text-lg font-normal grow" data-test-id="auth-title">{state.title || I18n.Auth()}</h1>
                <CodeTimer value={state?.codeLifeTime} onTimeout={onTimeout} />
            </div>
            <form className="input-form space-y-4" autoComplete="on" onSubmit={(e) => { e.preventDefault(); if (!disabled) buttonClick(); }}>
                <div>
                    <label className="form-label">{placeholder}</label>
                    <input
                        type={inputParams.type}
                        name={inputParams.name}
                        placeholder={placeholder}
                        className="form-control auth2-input"
                        autoComplete={inputParams.autoComplete}
                        required
                        disabled={!!state?.isSendingRequest}
                        ref={inputRef}
                        data-test-id="auth-login-input"
                    />
                </div>
                <div className={hint?.classes}>{hint?.hint}</div>
                {isEmailPhase && (
                    <>
                        <label className="auth-consent-row flex items-start gap-2">
                            <input
                                type="checkbox"
                                checked={pdConsent}
                                onChange={async (e) => {
                                    const checked = e.currentTarget.checked;
                                    setPdConsent(checked);
                                    if (checked && !csrfReady) {
                                        try {
                                            await startSession(mkConsent);
                                            setCsrfReady(true);
                                        } catch {
                                            setPdConsent(false);
                                            setCsrfReady(false);
                                        }
                                    }
                                }}
                                data-test-id="auth-consent-pd"
                                className="mt-1"
                            />
                            <span dangerouslySetInnerHTML={{__html: renderConsentPd()}} />
                        </label>
                        <label className="auth-consent-row flex items-start gap-2">
                            <input
                                type="checkbox"
                                checked={mkConsent}
                                onChange={(e) => setMkConsent(e.currentTarget.checked)}
                                data-test-id="auth-consent-marketing"
                                className="mt-1"
                            />
                            <span>{I18n.Consent_Marketing()}</span>
                        </label>
                        {!pdConsent && (
                            <div className="text-sm text-muted">{I18n.Consent_PD_Required()}</div>
                        )}
                    </>
                )}
                <div className="mb-3">
                    <button
                        type="submit"
                        className={button.classes}
                        disabled={disabled}
                        data-test-id="auth-submit-btn"
                    >
                        {button?.title}
                    </button>
                </div>
            </form>
        </div>
    );
};

