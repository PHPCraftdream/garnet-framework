import {readNumber} from '@common/Utils/Str/readNumber';
import {I18nFramework} from '@framework/I18nGen/I18nFramework';

export const Validators = {
    max_len: (value: string, args: string[], _el: HTMLInputElement): string | boolean => {
        const val = readNumber(args?.[0]);

        if (value.length > val) {
            return I18nFramework.Common_MaxLength([val + '']);
        }

        return true;
    },

    min_len: (value: string, args: string[], _el: HTMLInputElement): string | boolean => {
        const val = readNumber(args?.[0]);

        if (value.length < val) {
            return I18nFramework.Common_MinLength([val + '']);
        }

        return true;
    },

    len: (value: string, args: string[], _el: HTMLInputElement): string | boolean => {
        const min = readNumber(args?.[0]);
        const max = readNumber(args?.[1]);

        if (value.length > max || value.length < min ) {
            return I18nFramework.Common_Len([min + '', max + '']);
        }

        return true;
    },

    nameSymbols: (value: string, _args: string[], _el: HTMLInputElement): string | boolean => {
        if (!/^[\p{L} ,]+$/u.test(value)) {
            return I18nFramework.Common_IncorrectValue();
        }

        return true;
    },

    simpleText: (value: string, _args: string[], _el: HTMLInputElement): string | boolean => {
        if (!/^[\p{L}\p{P}\p{So} -~]*$/u.test(value)) {
            return I18nFramework.Common_IncorrectValue();
        }

        return true;
    },

    in_array: (value: string, args: string[], _el: HTMLInputElement): string | boolean => {
        const v = value + '';

        for (let arg of args) {
            if (v === arg + '') {
                return true;
            }
        }

        return I18nFramework.Common_IncorrectValue();
    },

    required: (value: string, _args: string[], _el: HTMLInputElement): string | boolean => {
        return value ? true : I18nFramework.Common_RequiredValue();
    },

    tzExists: (value: string, _args: string[], _el: HTMLInputElement): string | boolean => {
        const browserTimeZones = Intl.supportedValuesOf('timeZone');

        for (let tz of browserTimeZones) {
            if (tz === value) {
                return true;
            }
        }

        return I18nFramework.Common_IncorrectValue();
    },

    email: (value: string, _args: string[], _el: HTMLInputElement): string | boolean => {
        if (!value) {
            return true;
        }

        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        if (!emailRegex.test(value)) {
            return I18nFramework.Common_Email();
        }

        return true;
    },

    int: (value: string, _args: string[], _el: HTMLInputElement): string | boolean => {
        if (value === '' || value === null || value === undefined) {
            return true;
        }

        if (!Number.isInteger(Number(value))) {
            return I18nFramework.Common_Int();
        }

        return true;
    },

    min: (value: string, args: string[], _el: HTMLInputElement): string | boolean => {
        const min = readNumber(args?.[0]);
        const numValue = value === '' ? Infinity : Number(value);

        if (isNaN(numValue)) {
            return I18nFramework.Common_Int();
        }

        if (numValue < min) {
            return I18nFramework.Common_Min([min + '']);
        }

        return true;
    },

    minVal: (value: string, args: string[], _el: HTMLInputElement): string | boolean => {
        return Validators.min(value, args, _el);
    },

    max: (value: string, args: string[], _el: HTMLInputElement): string | boolean => {
        const max = readNumber(args?.[0]);
        const numValue = value === '' ? -Infinity : Number(value);

        if (isNaN(numValue)) {
            return I18nFramework.Common_Int();
        }

        if (numValue > max) {
            return I18nFramework.Common_Max([max + '']);
        }

        return true;
    },

    maxVal: (value: string, args: string[], _el: HTMLInputElement): string | boolean => {
        return Validators.max(value, args, _el);
    },

    url: (value: string, _args: string[], _el: HTMLInputElement): string | boolean => {
        if (!value) {
            return true;
        }

        try {
            return Boolean(new URL(value));
        } catch {
            return I18nFramework.Common_Url();
        }
    },

    alphanumeric: (value: string, _args: string[], _el: HTMLInputElement): string | boolean => {
        if (!value) {
            return true;
        }

        if (!/^[a-zA-Z0-9]+$/.test(value)) {
            return I18nFramework.Common_Alphanumeric();
        }

        return true;
    },

    match: (value: string, args: string[], _el: HTMLInputElement): string | boolean => {
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
    },

    pattern: (value: string, args: string[], _el: HTMLInputElement): string | boolean => {
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
    },
};
