import {asyncTextThen} from '@common/Api/Get/asyncTextThen';

/** Implements getHtml for the shared frontend API. */
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
