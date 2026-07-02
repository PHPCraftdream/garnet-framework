import {readNumber} from '@common/Utils/Str/readNumber';
import {I18nFramework} from '@framework/I18nGen/I18nFramework';

export class Validators {
    static max_len = (value: string, args: string[], el: HTMLInputElement): string | boolean => {
        const val = readNumber(args?.[0]);

        if (value.length > val) {
            return I18nFramework.Common_MaxLength([val + '']);
        }

        return true;
    }

    static min_len = (value: string, args: string[], el: HTMLInputElement): string | boolean => {
        const val = readNumber(args?.[0]);

        if (value.length < val) {
            return I18nFramework.Common_MinLength([val + '']);
        }

        return true;
    }

    static len = (value: string, args: string[], el: HTMLInputElement): string | boolean => {
        const min = readNumber(args?.[0]);
        const max = readNumber(args?.[1]);

        if (value.length > max || value.length < min ) {
            return I18nFramework.Common_Len([min + '', max + '']);
        }

        return true;
    }

    static nameSymbols = (value: string, args: string[], el: HTMLInputElement): string | boolean => {
        if (!/^[\p{L} ,]+$/u.test(value)) {
            return I18nFramework.Common_IncorrectValue();
        }

        return true;
    }

    static simpleText = (value: string, args: string[], el: HTMLInputElement): string | boolean => {
        if (!/^[\p{L}\p{P}\p{So} -~]*$/u.test(value)) {
            return I18nFramework.Common_IncorrectValue();
        }

        return true;
    }

    static in_array = (value: string, args: string[], el: HTMLInputElement): string | boolean => {
        const v = value + '';

        for (let arg of args) {
            if (v === arg + '') {
                return true;
            }
        }

        return I18nFramework.Common_IncorrectValue();
    }

    static required = (value: string, args: string[], el: HTMLInputElement): string | boolean => {
        return !!value ? true : I18nFramework.Common_RequiredValue();
    }

    static tzExists = (value: string, args: string[], el: HTMLInputElement): string | boolean => {
        const browserTimeZones = Intl.supportedValuesOf('timeZone');

        for (let tz of browserTimeZones) {
            if (tz === value) {
                return true;
            }
        }

        return I18nFramework.Common_IncorrectValue();
    }

    static email = (value: string, args: string[], el: HTMLInputElement): string | boolean => {
        if (!value) {
            return true;
        }

        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        if (!emailRegex.test(value)) {
            return I18nFramework.Common_Email();
        }

        return true;
    }

    static int = (value: string, args: string[], el: HTMLInputElement): string | boolean => {
        if (value === '' || value === null || value === undefined) {
            return true;
        }

        if (!Number.isInteger(Number(value))) {
            return I18nFramework.Common_Int();
        }

        return true;
    }

    static min = (value: string, args: string[], el: HTMLInputElement): string | boolean => {
        const min = readNumber(args?.[0]);
        const numValue = value === '' ? Infinity : Number(value);

        if (isNaN(numValue)) {
            return I18nFramework.Common_Int();
        }

        if (numValue < min) {
            return I18nFramework.Common_Min([min + '']);
        }

        return true;
    }

    static minVal = (value: string, args: string[], el: HTMLInputElement): string | boolean => {
        return Validators.min(value, args, el);
    }

    static max = (value: string, args: string[], el: HTMLInputElement): string | boolean => {
        const max = readNumber(args?.[0]);
        const numValue = value === '' ? -Infinity : Number(value);

        if (isNaN(numValue)) {
            return I18nFramework.Common_Int();
        }

        if (numValue > max) {
            return I18nFramework.Common_Max([max + '']);
        }

        return true;
    }

    static maxVal = (value: string, args: string[], el: HTMLInputElement): string | boolean => {
        return Validators.max(value, args, el);
    }

    static url = (value: string, args: string[], el: HTMLInputElement): string | boolean => {
        if (!value) {
            return true;
        }

        try {
            new URL(value);
            return true;
        } catch {
            return I18nFramework.Common_Url();
        }
    }

    static alphanumeric = (value: string, args: string[], el: HTMLInputElement): string | boolean => {
        if (!value) {
            return true;
        }

        if (!/^[a-zA-Z0-9]+$/.test(value)) {
            return I18nFramework.Common_Alphanumeric();
        }

        return true;
    }

    static match = (value: string, args: string[], el: HTMLInputElement): string | boolean => {
        const targetSelector = args?.[0];

        if (!targetSelector) {
            return true;
        }

        const targetEl = document.querySelector(targetSelector) as HTMLInputElement;

        if (!targetEl) {
            return true;
        }

        if (value !== targetEl.value) {
            return I18nFramework.Common_Match();
        }

        return true;
    }

    static pattern = (value: string, args: string[], el: HTMLInputElement): string | boolean => {
        if (!value) {
            return true;
        }

        const pattern = args?.[0];

        if (!pattern) {
            return true;
        }

        const flags = args?.[1] || '';

        try {
            const regex = new RegExp(pattern, flags);

            if (!regex.test(value)) {
                return I18nFramework.Common_Pattern();
            }

            return true;
        } catch {
            return I18nFramework.Common_Pattern();
        }
    }
}
