import {PrefetchCache} from '@common/Api/PrefetchCache';
/** Shares pending and completed document requests between navigation actions. */
export const documentCache = new PrefetchCache<string>();
