interface TsParts {
    year: string;
    month: string;
    day: string;
    hour: string;
    minute: string;
}

/** Extracts localized calendar and clock fields from a timestamp. */
export function tsToParts(ts: number | string, tz: string): TsParts {
    const n = Number(ts);
    const parts = new Intl.DateTimeFormat('en-CA', {
        timeZone: tz,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false,
    }).formatToParts(new Date(n * 1000));
    const get = (type: string): string => parts.find(p => p.type === type)?.value ?? '00';
    return {
        year: get('year'),
        month: get('month'),
        day: get('day'),
        hour: get('hour') === '24' ? '00' : get('hour'),
        minute: get('minute'),
    };
}
