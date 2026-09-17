import {asyncTextThen} from '@common/Api/Get/asyncTextThen';

export const getHtml = (url: string, signal?: AbortSignal): Promise<string> => {
    const response = fetch(url, {
        method: 'GET',
        headers: {
            'Accept': 'application/html',
            'Content-Type': 'application/html'
        },
        signal,
    });

    return response.then(asyncTextThen) as Promise<string>;
};
