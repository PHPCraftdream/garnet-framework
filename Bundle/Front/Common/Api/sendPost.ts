import {asyncJsonThen} from '@common/Api/asyncJsonThen';
import {IApiSuccessResponse, IGarnetWindow} from '@common/Models';

const w: IGarnetWindow = window as IGarnetWindow;

export const sendPost = <Request extends {}, Response extends {}>(url: string, params: Request): Promise<IApiSuccessResponse<Response>> => {
    const newParams = {...params};

    if (w.__GARNET_CSRF__) {
        newParams['CSRF_TOKEN'] = w.__GARNET_CSRF__;
    }

    const response = fetch(url, {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(newParams),
    });

    return response.then((r) => asyncJsonThen<Response>(r));
}
