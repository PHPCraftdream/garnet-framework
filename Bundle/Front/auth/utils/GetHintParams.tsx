import {IAuthData} from '@framework/auth/Models';
import {EPhase} from '@framework/auth/Enums';
import {I18nFramework as I18n} from '@framework/I18nGen/I18nFramework';

export const getHintParams = (state?: IAuthData): { hint: string, classes: string} => {
    const cls = 'text-sm mb-3';

    switch (state?.phase) {
        case EPhase.INPUT_EMAIL: {
            return {hint: I18n.Auth_EmailHint(), classes: `${cls} text-muted`};
        }
        case EPhase.INPUT_EMAIL_AFTER_TIMEOUT: {
            let newText = `${I18n.Auth_CodeOutdated()}. ${I18n.Auth_RequestNewCode()}. ${I18n.Auth_EmailHint()}`;
            return {hint: newText, classes: `${cls} text-danger`};
        }
        case EPhase.INPUT_EMAIL_AFTER_FAIL_TRIES: {
            let newText = `${I18n.Auth_ExhaustedInputAttempts()}. ${I18n.Auth_RequestNewCode()}. ${I18n.Auth_EmailHint()}`;
            return {hint: newText, classes: `${cls} text-danger`};
        }
        case EPhase.INPUT_EMAIL_WRONG_VALUE: {
            let newText = `${I18n.Common_IncorrectValue()}. ${I18n.Auth_EmailHint()}`;
            return {hint: newText, classes: `${cls} text-danger`};
        }
        case EPhase.INPUT_EMAIL_REQUEST_ERROR: {
            // Prepend the server-supplied reason (e.g. "Too many code
            // requests...") when HandleRequestCode caught a RespError
            // from a 4xx/5xx response. Falls back to the generic
            // wording when there's nothing useful from the server.
            const base = `${I18n.Common_RequestError()}. ${I18n.Auth_EmailHint()}`;
            const newText = state?.hint ? `${state.hint} ${base}` : base;
            return {hint: newText, classes: `${cls} text-danger`};
        }
        case EPhase.INPUT_CODE: {
            return {hint: I18n.Auth_CodeHint(), classes: `${cls} text-muted`};
        }
        case EPhase.INPUT_CODE_FAIL: {
            const newText = `${I18n.Auth_TriesRemains([state?.codeInputTries])}. ${I18n.Auth_CodeHint()}`;
            return {hint: newText, classes: `${cls} text-danger`};
        }
        case EPhase.INPUT_CODE_WRONG_VALUE: {
            const newText = `${I18n.Common_IncorrectValue()}. ${I18n.Auth_CodeHint()}`;
            return {hint: newText, classes: `${cls} text-danger`};
        }
        case EPhase.INPUT_CODE_REQUEST_ERROR: {
            let newText = `${I18n.Auth_CodeHint()}`;

            if (state.hint) {
                newText = `${state?.hint}. ${newText}`;
            }

            return {hint: newText, classes: `${cls} text-danger`};
        }
        default:
            const _: never = null as never;
    }
}
