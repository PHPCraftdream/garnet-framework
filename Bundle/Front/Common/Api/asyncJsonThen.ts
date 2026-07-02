import {RespError} from '@common/Api/RespError';
import {IApiResponse, IApiSuccessResponse} from '@common/Models';
import {isMaintenance503} from '@common/Api/maintenance503';

export const asyncJsonThen = async <T = Record<string, unknown>>(d: Response): Promise<IApiSuccessResponse<T>> => {
    // Maintenance: a 503 carries the HTML maintenance page, not JSON. Raise the
    // shared toast and bail BEFORE json() throws an opaque parse error.
    if (isMaintenance503(d.status)) {
        const maintErr = new RespError('Common_Maintenance');
        maintErr.response = {status: 503, maintenance: true} as unknown as IApiResponse<T>;
        throw maintErr;
    }

    const data = await d.json() as IApiResponse<T>;

    if (d.ok) {
        return data as IApiSuccessResponse<T>;
    }

    const message = ('error' in data && data.error ? data.error : null)
        || ('message' in data && data.message ? data.message : null)
        || 'Common_RequestError';

    const error = new RespError(message as string);
    error.response = data;

    throw error;
};
