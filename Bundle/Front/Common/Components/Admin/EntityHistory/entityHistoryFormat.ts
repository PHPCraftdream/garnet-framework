import {formatTs} from '@common/Utils/Time/DateUtils';

/** TZ-aware history timestamp formatter — uses user's TZ via formatTs (AGENTS.md §12). */
export const formatHistoryTime = (ts: number): string => {
    if (!ts) return '';
    return formatTs(ts);
};

export const renderHistoryValue = (v: unknown): string => {
    if (v === null || v === undefined) return '∅';
    if (typeof v === 'object') return JSON.stringify(v);
    return String(v);
};
