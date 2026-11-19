import {asyncJsonThen} from '@common/Api/Get/asyncJsonThen';
import {IApiResponse} from '@common/Support/Models';

const parseJsonResponse = <T extends {}>(response: Response) => asyncJsonThen<T>(response);

export const getJson = <Response extends {}>(url: string): Promise<IApiResponse<Response>> => {
    const response = fetch(url, {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        },
    });

    return response.then(parseJsonResponse<Response>);
}
