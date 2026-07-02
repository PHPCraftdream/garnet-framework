import {asyncTextThen} from '@common/Api/asyncTextThen';

export const getHtml = (url: string): Promise<string> => {
    const response = fetch(url, {
        method: 'GET',
        headers: {
            'Accept': 'application/html',
            'Content-Type': 'application/html'
        },
    });

    return response.then(asyncTextThen) as Promise<string>;
};
