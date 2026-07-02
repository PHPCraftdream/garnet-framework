import {IApiResponse, IApiSuccessResponse} from '@common/Models';

export function isApiSuccess<T>(response: IApiResponse<T>): response is IApiSuccessResponse<T> {
    return !('message' in response) || response.ok === true;
}
