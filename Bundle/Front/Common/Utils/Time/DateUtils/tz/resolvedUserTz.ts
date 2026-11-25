import {dateUtilsShared} from '@common/Utils/Time/DateUtils/shared/dateUtilsShared';

const userTz = dateUtilsShared.userTz;
const browserTz = dateUtilsShared.browserTz;

/** Returns the resolvedUserTz date utility result for its configured timezone. */
export function resolvedUserTz(): string {
    return userTz() ?? browserTz;
}
