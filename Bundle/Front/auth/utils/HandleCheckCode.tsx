import {ICheckCodeRequest, ICheckCodeRequestResponse, TInputRef, TSetAuthState} from '@framework/auth/Models';
import {EPhase} from '@framework/auth/Enums';
import {sendPost} from '@common/Api/sendPost';
import {goTo} from '@common/Dom/Nav/GoTo';
import {isApiSuccess} from '@common/Api/isApiSuccess';

/**
 * Code verification handler.
 */
export const handleCheckCode = (setData: TSetAuthState, inputRef: TInputRef) => {
    if (!inputRef.current.validity?.valid) {
        setData((state) => ({...state, phase: EPhase.INPUT_CODE_WRONG_VALUE}));
        return;
    }

    setData((state) => ({...state, isSendingRequest: true}));

    const code = inputRef.current.value;
    const promise = sendPost<ICheckCodeRequest, ICheckCodeRequestResponse>(
        window.location.href,
        {code}
    );

    promise.then((response) => {
        if (!isApiSuccess(response)) {
            // A 2xx HTTP response whose JSON body is still an error shape
            // (has `message`, `ok !== true`) — same "form frozen forever"
            // risk as an uncaught rejection if isSendingRequest is left
            // true here.
            setData((state) => ({...state, isSendingRequest: false, phase: EPhase.INPUT_CODE_REQUEST_ERROR}));
            return;
        }

        if (response.success) {
            goTo(window.location.href);

            return;
        }

        const codeInputTries = Number(response.codeInputTries) || 0;

        if (codeInputTries === 0 || response.timeout) {
            const phase = response.timeout ? EPhase.INPUT_EMAIL_AFTER_TIMEOUT : EPhase.INPUT_EMAIL_AFTER_FAIL_TRIES;

            setData((state) => {
                inputRef.current.value = '';
                setTimeout(() => inputRef.current.focus(), 100);

                return {...state, isSendingRequest: false, phase, codeInputTries, codeLifeTime: 0};
            });

            return;
        }

        setData((state) => {
            inputRef.current.value = '';
            setTimeout(() => inputRef.current.focus(), 100);

            return {...state, isSendingRequest: false, phase: EPhase.INPUT_CODE_FAIL, codeInputTries};
        });
    }).catch((err: unknown) => {
        // asyncJsonThen throws a RespError (network failure, CSRF not ready
        // yet, 4xx/5xx, maintenance 503, ...) whenever the request itself
        // never reaches the success/failure branches above. Without this
        // catch, isSendingRequest never resets to false: the magic-link
        // auto-verify path (Auth2Island's effect calls this with no user
        // interaction) would leave the form permanently disabled with zero
        // feedback — exactly the "nothing happens for a long time" report.
        // Mirrors HandleRequestCode's catch.
        const serverMsg = (err && typeof err === 'object' && 'message' in err)
            ? String((err as { message?: unknown }).message ?? '')
            : '';

        setData((state) => {
            return {...state, isSendingRequest: false, phase: EPhase.INPUT_CODE_REQUEST_ERROR, hint: serverMsg || undefined};
        });
    });
};
