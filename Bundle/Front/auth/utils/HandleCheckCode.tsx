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
    })
};
