import {formatTs} from '@common/Utils/Time/DateUtils/format/formatTs';

/** Formats a history timestamp in the user's configured timezone. */
export const formatHistoryTime = (ts: number): string => {
    if (!ts) return '';
    return formatTs(ts);
};
