import * as React from 'react';
import {useCallback, useRef, useState} from 'react';
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

export const Auth2Island: React.FC<Partial<IAuthData>> = (props) => {
    const [state, setData] = useState<IAuthData>({phase: EPhase.INPUT_EMAIL, ...props});
    const inputRef: TInputRef = useRef<HTMLInputElement>(null);

    const [pdConsent, setPdConsent] = useState(false);
    const [mkConsent, setMkConsent] = useState(false);
    const [csrfReady, setCsrfReady] = useState<boolean>(!!(w as any).__GARNET_CSRF__);

    const renderConsentPd = (): string => renderMarkdownLinks(I18n.Consent_PD());

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
    const isEmailPhase = [
        EPhase.INPUT_EMAIL,
        EPhase.INPUT_EMAIL_AFTER_TIMEOUT,
        EPhase.INPUT_EMAIL_AFTER_FAIL_TRIES,
        EPhase.INPUT_EMAIL_WRONG_VALUE,
        EPhase.INPUT_EMAIL_REQUEST_ERROR,
    ].includes(state?.phase);
    // pdConsent/csrfReady only gate the email phase, where the consent
    // checkboxes actually render — they're component-local state that
    // resets on every fresh page load, so requiring them in the code
    // phase too would permanently disable the submit button for anyone
    // who lands directly on the code-entry screen (a fresh tab/device
    // opening a magic link, or reloading after a failed auto-verify)
    // without ever having ticked a checkbox they can't even see.
    const disabled = !!state?.isSendingRequest || (isEmailPhase && (!pdConsent || !csrfReady));

    return (
        <div className="w-full m-auto" style={{maxWidth: '500px'}}>
            <div className="flex mb-3">
                <h1 className="text-lg font-normal grow" data-test-id="auth-title">{state.title || I18n.Auth()}</h1>
                <CodeTimer value={state?.codeLifeTime} onTimeout={onTimeout} />
            </div>
            {state?.isSendingRequest && (
                // Processing a manual submit: show a loader instead of
                // leaving the page looking frozen while the request is in
                // flight. The form below is hidden via CSS (not unmounted)
                // rather than conditionally rendered —
                // conditional rendering would tear down and later remount
                // the <input>, wiping out any value handleRequestCode /
                // handleCheckCode set on it imperatively via inputRef
                // (e.g. preserving the typed email across a failed request).
                <div className="flex items-center gap-2 py-4 text-muted" data-test-id="auth-loading">
                    <span className="common-spinner" aria-hidden="true" />
                    <span>{I18n.Auth_Verifying()}</span>
                </div>
            )}
            <form
                className={`input-form space-y-4${state?.isSendingRequest ? ' hidden' : ''}`}
                autoComplete="on"
                onSubmit={(e) => { e.preventDefault(); if (!disabled) buttonClick(); }}
            >
                <div>
                    <label className="form-label">{placeholder}</label>
                    <input
                        type={inputParams.type}
                        name={inputParams.name}
                        placeholder={placeholder}
                        className="form-control auth2-input"
                        autoComplete={inputParams.autoComplete}
                        required
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

