import {sprintf} from '@common/Utils/Str/Sprintf';
import {IGarnetWindow} from '@common/Models';

const w: IGarnetWindow = window as IGarnetWindow;

export class I18nBase {
    protected lastObj = null;

    constructor(protected langObjects: object[]) {
    }

    protected getLang = () => {
        return w.__GARNET_UI_LANG__ || 'RU';
    }

    t = (key: string, args: (string | number)[] = []): string => {
        const value = this.getObj()?.[key];
        if (value === undefined) {
            return key;
        }
        return args.length > 0 ? sprintf(value, args) : value;
    };

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
