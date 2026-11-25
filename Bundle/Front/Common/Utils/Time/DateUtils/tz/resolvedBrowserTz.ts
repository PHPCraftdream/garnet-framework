import {dateUtilsShared} from '@common/Utils/Time/DateUtils/shared/dateUtilsShared';

const browserTz = dateUtilsShared.browserTz;

/** Returns the resolvedBrowserTz date utility result for its configured timezone. */
export function resolvedBrowserTz(): string {
    return browserTz;
}
