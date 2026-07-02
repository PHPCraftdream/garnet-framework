import {IAuthData} from '@framework/auth/Models';
import {EPhase} from '@framework/auth/Enums';

export const getInputParams = (state?: IAuthData): { type: string; name: string; autoComplete: string } => {
    const cls = 'small fs-7 mb-3';

    switch (state?.phase) {
        case EPhase.INPUT_EMAIL:
        case EPhase.INPUT_EMAIL_AFTER_TIMEOUT:
        case EPhase.INPUT_EMAIL_AFTER_FAIL_TRIES:
        case EPhase.INPUT_EMAIL_WRONG_VALUE:
        case EPhase.INPUT_EMAIL_REQUEST_ERROR: {
            return {type: 'email', name: 'email', autoComplete: 'email'};
        }
        case EPhase.INPUT_CODE:
        case EPhase.INPUT_CODE_FAIL:
        case EPhase.INPUT_CODE_WRONG_VALUE:
        case EPhase.INPUT_CODE_REQUEST_ERROR: {
            return {type: 'text', name: 'one-time-code', autoComplete: 'one-time-code'};
        }
        default:
            const _: never = null as never;
    }
}
