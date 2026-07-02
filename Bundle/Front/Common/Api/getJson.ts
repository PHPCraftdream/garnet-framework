import {asyncJsonThen} from '@common/Api/asyncJsonThen';
import {IApiResponse} from '@common/Models';

export const getJson = <Response extends {}>(url: string): Promise<IApiResponse<Response>> => {
    const response = fetch(url, {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        },
    });

    return response.then((r) => asyncJsonThen<Response>(r));
}
