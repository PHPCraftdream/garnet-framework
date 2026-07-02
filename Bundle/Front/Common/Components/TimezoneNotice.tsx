import * as React from 'react';
import {I18nFramework as t} from '@framework/I18nGen/I18nFramework';
import {
    resolvedUserTz,
    resolvedBrowserTz,
    userTzDiffersFromBrowser,
    formatTs,
    tzOffsetMinutes,
} from '@common/Utils/DateUtils';
import {Banner} from './Banner';

interface Props {
    /** Hide the calm info banner; only show the warn variant on TZ mismatch. */
    warnOnly?: boolean;
    /** Hide the warn variant; only show the calm info banner. */
    infoOnly?: boolean;
}

function formatDiff(minutes: number): string {
    if (minutes === 0) return t.Tz_Diff_Same();
    const abs = Math.abs(minutes);
    const h = Math.floor(abs / 60);
    const m = abs % 60;
    const amount = m === 0 ? t.Tz_Diff_Hours([String(h)]) : t.Tz_Diff_HoursMinutes([String(h), String(m)]);
    return minutes > 0 ? t.Tz_Diff_Ahead([amount]) : t.Tz_Diff_Behind([amount]);
}

/**
 * Two-state notice for time-critical pages.
 *
 * - Calm info banner: «Times shown in your profile timezone: <tz>». Always
 *   on so users never wonder which clock they're reading.
 * - Warn banner: appears when the browser TZ differs from the profile TZ.
 *   Spells out *now* in both timezones plus the exact offset, so the user
 *   can decide whether to update their profile or trust the page as-is.
 */
export const TimezoneNotice: React.FC<Props> = ({warnOnly = false, infoOnly = false}) => {
    const userTzName = resolvedUserTz();
    const browserTzName = resolvedBrowserTz();
    const mismatched = userTzDiffersFromBrowser();

    const nowSec = Math.floor(Date.now() / 1000);
    const nowInBrowser = formatTs(nowSec, {tz: browserTzName});
    const nowInUser = formatTs(nowSec, {tz: userTzName});
    const offsetMin = mismatched ? tzOffsetMinutes(nowSec, userTzName) : 0;

    return (
        <>
            {!warnOnly && (
                <Banner variant="info" icon="🕒" dataTestId="tz-banner-info">
                    {t.Tz_Info([userTzName])}
                </Banner>
            )}
            {!infoOnly && mismatched && (
                <Banner variant="warn" title={t.Tz_Mismatch_Title()} dataTestId="tz-banner-warn">
                    <div className="tz-mismatch-row">
                        <span className="tz-chip-inline tz-chip-active">
                            <span className="tz-chip-check" aria-hidden="true">✓</span>
                            <span className="tz-chip-label-text">{t.Tz_Mismatch_Footer([userTzName])}:</span>
                            <span className="tz-chip-time">{nowInUser}</span>
                        </span>
                        <span className="tz-diff-pill" aria-hidden="true">{formatDiff(offsetMin)}:</span>
                        <span className="tz-chip-inline tz-chip-muted">
                            <span className="tz-chip-label-text">{t.Tz_Mismatch_BrowserLabel()}:</span>
                            <span className="tz-chip-name">{browserTzName}</span>
                            <span className="tz-chip-sep">·</span>
                            <span className="tz-chip-time">{nowInBrowser}</span>
                        </span>
                    </div>
                </Banner>
            )}
        </>
    );
};
