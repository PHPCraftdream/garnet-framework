// noinspection DuplicatedCode
import {I18nDataRU} from './I18nDataRU';
import {I18nDataEN} from './I18nDataEN';
import {I18nBase} from '@common/Utils/I18nBase';

type t = (args?: (string|number)[]) => string;

class I18n extends I18nBase {
    hello_foreground: t = (a = []) => this.t('hello_foreground', a);
}

export const I18nForeground = new I18n([I18nDataRU, I18nDataEN]);
