import {getHtml} from '@common/Api/Get/getHtml';
import {documentCache} from './documentCache';
/** Starts a background fetch for a document URL. */
export const prefetchDocument = (url: string): void => { documentCache.prefetch(url, signal => getHtml(url, signal)); };
