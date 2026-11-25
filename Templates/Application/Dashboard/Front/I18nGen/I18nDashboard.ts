// noinspection DuplicatedCode
import {I18nDataRU} from './I18nDataRU';
import {I18nDataEN} from './I18nDataEN';
import {I18nBase} from '@common/Utils/I18nBase';

/** Translation lookup function signature. */
type t = (args?: (string|number)[]) => string;

/** Provides typed access to this bundle's translations. */
class I18n extends I18nBase {
    /** Translation lookup for `hello_dashboard`. */
    hello_dashboard: t = (a = []) => this.t('hello_dashboard', a);
}

/** Active translations for this bundle. */
export const I18nDashboard = new I18n([I18nDataRU, I18nDataEN]);
