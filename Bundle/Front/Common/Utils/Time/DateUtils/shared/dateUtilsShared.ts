import {appLocale} from './appLocale';
import {browserTz} from './browserTz';
import {tsToParts} from './tsToParts';
import {tzWallToUnix} from './tzWallToUnix';
import {userTz} from './userTz';

/** Internal helper bundle consumed by the DateUtils mini-modules. */
export const dateUtilsShared = {appLocale, userTz, browserTz, tsToParts, tzWallToUnix};
