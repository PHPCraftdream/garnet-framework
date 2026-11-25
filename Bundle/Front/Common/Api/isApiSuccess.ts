import {IApiResponse, IApiSuccessResponse} from '@common/Support/Models';

/** Checks whether an API response represents success. */
export function isApiSuccess<T>(response: IApiResponse<T>): response is IApiSuccessResponse<T> {
    return !('message' in response) || response.ok === true;
}
