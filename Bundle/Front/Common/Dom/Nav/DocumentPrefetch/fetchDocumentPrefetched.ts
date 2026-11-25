import {getHtml} from '@common/Api/Get/getHtml';
import {documentCache} from './documentCache';

/** Returns prefetched document HTML, retrying with a direct request on failure. */
export const fetchDocumentPrefetched = (url: string): Promise<string> =>
    documentCache.consume(url, signal => getHtml(url, signal))
        .catch(() => getHtml(url));
