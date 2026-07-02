import {EPhase} from '@framework/auth/Enums';
import {I18nFramework as I18n} from '@framework/I18nGen/I18nFramework';

export const getInputPlaceholder = (phase?: EPhase) => {
    switch (phase) {
        case EPhase.INPUT_EMAIL:
        case EPhase.INPUT_EMAIL_AFTER_TIMEOUT:
        case EPhase.INPUT_EMAIL_AFTER_FAIL_TRIES:
        case EPhase.INPUT_EMAIL_WRONG_VALUE:
        case EPhase.INPUT_EMAIL_REQUEST_ERROR: {
            return 'Email';
        }
        case EPhase.INPUT_CODE:
        case EPhase.INPUT_CODE_FAIL:
        case EPhase.INPUT_CODE_WRONG_VALUE:
        case EPhase.INPUT_CODE_REQUEST_ERROR: {
            return I18n.Auth_CodePlaceholder();
        }
        default:
            const _: never = null as never;
    }
}
