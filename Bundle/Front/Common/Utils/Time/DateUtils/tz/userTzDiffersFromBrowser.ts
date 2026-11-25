import {dateUtilsShared} from '@common/Utils/Time/DateUtils/shared/dateUtilsShared';

const userTz = dateUtilsShared.userTz;
const browserTz = dateUtilsShared.browserTz;

/** Returns whether the account timezone differs from the browser timezone. */
export function userTzDiffersFromBrowser(): boolean {
    const u = userTz();
    return !!u && u !== browserTz;
}
