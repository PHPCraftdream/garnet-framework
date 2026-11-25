import {appLocale} from '@common/Utils/Time/DateUtils/shared/appLocale';
import {userTz} from '@common/Utils/Time/DateUtils/shared/userTz';
import {browserTz} from '@common/Utils/Time/DateUtils/shared/browserTz';

/** Options controlling timestamp date formatting. */
export interface FormatTsOptions {
    dateOnly?: boolean;
    tz?: string;
    weekday?: boolean;
}


/** Formats a Unix timestamp using the selected locale and timezone. */
export function formatTs(ts: number | string | null | undefined, opts?: FormatTsOptions): string {
    const n = Number(ts);
    if (!n) return '—';
    const tz = opts?.tz ?? userTz() ?? browserTz;
    const fmt = new Intl.DateTimeFormat(appLocale(), {
        timeZone: tz,
        ...(opts?.weekday ? {weekday: 'short'} : {}),
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        ...(opts?.dateOnly ? {} : {hour: '2-digit', minute: '2-digit', hourCycle: 'h23'}),
    });
    return fmt.format(new Date(n * 1000));
}
