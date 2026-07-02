import {IAuthData} from '@framework/auth/Models';
import {EPhase} from '@framework/auth/Enums';
import {I18nFramework as I18n} from '@framework/I18nGen/I18nFramework';

export const getButtonParams = (state?: IAuthData): { title: string, classes: string } => {
    const cls = 'btn w-full py-2';

    switch (state?.phase) {
        case EPhase.INPUT_EMAIL:
        case EPhase.INPUT_EMAIL_AFTER_TIMEOUT:
        case EPhase.INPUT_EMAIL_AFTER_FAIL_TRIES:
        case EPhase.INPUT_EMAIL_WRONG_VALUE:
        case EPhase.INPUT_EMAIL_REQUEST_ERROR: {
            return {title: I18n.Auth_RequestCode(), classes: `${cls} btn-primary`};
        }
        case EPhase.INPUT_CODE:
        case EPhase.INPUT_CODE_FAIL:
        case EPhase.INPUT_CODE_WRONG_VALUE:
        case EPhase.INPUT_CODE_REQUEST_ERROR: {
            return {title: I18n.Auth_AuthWithCode(), classes: `${cls} btn-success`};
        }
        default:
            const _: never = null as never;
    }
}
