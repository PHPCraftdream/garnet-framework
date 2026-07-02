import {IAuthData, ICodeRequest, ICodeRequestResponse, TInputRef, TSetAuthState} from '@framework/auth/Models';
import {EPhase} from '@framework/auth/Enums';
import {sendPost} from '@common/Api/sendPost';
import {goTo} from '@common/Dom/Nav/GoTo';

/**
 * Request-code handler.
 */
export const handleRequestCode = (setData: TSetAuthState, inputRef: TInputRef) => {
    if (!inputRef.current.validity?.valid) {
        setData((state) => ({...state, phase: EPhase.INPUT_EMAIL_WRONG_VALUE}));
        return;
    }

    setData((state) => ({...state, isSendingRequest: true}));

    const auth_email = inputRef.current.value;
    const promise = sendPost<ICodeRequest, ICodeRequestResponse>(
        window.location.href,
        {auth_email}
    );

    promise.then((response: ICodeRequestResponse) => {
        if ((response as any).success) {
            goTo(window.location.href);
            return;
        }

        setData((state) => {
            const newState: IAuthData = {...state, isSendingRequest: false, phase: EPhase.INPUT_CODE};

            newState.hint = response.message;
            newState.codeInputTries = Number(response.codeInputTries) || 3;
            newState.codeLifeTime = Number(response.codeLifeTime);

            return newState;
        });
        // Moving to the code phase — the same <input> is reused for the
        // 8-digit token, so clear whatever the user typed in the email
        // field.
        if (inputRef.current) inputRef.current.value = '';
    }).catch((err: unknown) => {
        // asyncJsonThen throws a RespError whose .message comes from the
        // server body ({message: "..."} on 4xx/5xx, e.g. 429 rate-limit).
        // Surfacing it in the hint tells the user *why* the request
        // failed instead of the generic "Common_RequestError" fallback.
        const serverMsg = (err && typeof err === 'object' && 'message' in err)
            ? String((err as { message?: unknown }).message ?? '')
            : '';
        setData((state) => {
            return {
                ...state,
                isSendingRequest: false,
                phase: EPhase.INPUT_EMAIL_REQUEST_ERROR,
                hint: serverMsg || undefined,
            };
        });
        // Intentionally do NOT clear the value on error — retyping the
        // address each time a request fails is the worst UX. The phase
        // stays on INPUT_EMAIL_*, so the same input is still semantically
        // an email field.
    }).finally(() => {
        setTimeout(() => inputRef.current?.focus(), 100);
    });
};
