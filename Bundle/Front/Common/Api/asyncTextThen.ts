import {RespError} from '@common/Api/RespError';
import {isMaintenance503} from '@common/Api/maintenance503';

export const asyncTextThen = async (d: Response): Promise<string> => {
    // Maintenance (503): raise the shared toast before treating the body as a
    // normal error response (e.g. hot-click AJAX navigation during maintenance).
    if (isMaintenance503(d.status)) {
        const maintErr = new RespError('Common_Maintenance');
        maintErr.response = await d.text();
        throw maintErr;
    }

    const data = await d.text();

    if (d.ok) {
        return data;
    }

    const error = new RespError('Common_RequestError');
    error.response = data;

    throw error;
};
