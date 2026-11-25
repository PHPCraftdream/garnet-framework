import {sprintf} from '@common/Utils/Str/Sprintf';
import {IGarnetWindow} from '@common/Support/Models';

const w: IGarnetWindow = window as IGarnetWindow;

export class I18nBase {
    protected lastObj = null;

    /** Creates the translation resolver with its language data. */
    constructor(protected langObjects: object[]) {
    }

    /** Returns the active interface language, defaulting to Russian. */
    protected getLang = () => {
        return w.__GARNET_UI_LANG__ || 'RU';
    }

    /** Returns the translation for a key, formatting it with optional arguments. */
    t = (key: string, args: (string | number)[] = []): string => {
        const value = this.getObj()?.[key];
        if (value === undefined) {
            return key;
        }
        return args.length > 0 ? sprintf(value, args) : value;
    };

    /** Finds the translation object matching the active language. */
    protected getObj = () => {
        const lang = this.getLang();

        if (this.lastObj && this.lastObj.lang === lang) {
            return this.lastObj;
        }

        for (let i = 0; i < this.langObjects.length; i++) {
            const trObj = this.langObjects[i];
            const langObj = trObj?.['lang'];

            if (langObj == lang) {
                this.lastObj = trObj;

                return trObj;
            }
        }

        return {};
    }
}
