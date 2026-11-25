import {readNumber} from '@common/Utils/Str/readNumber';
import {I18nFramework} from '@framework/I18nGen/I18nFramework';

export const Validators = {
    /** Accepts values within the configured maximum length. */
    max_len: (value: string, args: readonly string[], _el: HTMLInputElement): string | boolean => {
        const val = readNumber(args?.[0]);

        if (value.length > val) {
            return I18nFramework.Common_MaxLength([val + '']);
        }

        return true;
    },

    /** Accepts values within the configured minimum length. */
    min_len: (value: string, args: readonly string[], _el: HTMLInputElement): string | boolean => {
        const val = readNumber(args?.[0]);

        if (value.length < val) {
            return I18nFramework.Common_MinLength([val + '']);
        }

        return true;
    },

    /** Accepts values whose length falls within the configured range. */
    len: (value: string, args: readonly string[], _el: HTMLInputElement): string | boolean => {
        const min = readNumber(args?.[0]);
        const max = readNumber(args?.[1]);

        if (value.length > max || value.length < min) {
            return I18nFramework.Common_Len([min + '', max + '']);
        }

        return true;
    },

    /** Accepts names containing letters, spaces, and commas. */
    nameSymbols: (value: string, _args: readonly string[], _el: HTMLInputElement): string | boolean => {
        if (!/^[\p{L} ,]+$/u.test(value)) {
            return I18nFramework.Common_IncorrectValue();
        }

        return true;
    },

    /** Accepts text containing letters, punctuation, symbols, and basic ASCII. */
    simpleText: (value: string, _args: readonly string[], _el: HTMLInputElement): string | boolean => {
        if (!/^[\p{L}\p{P}\p{So} -~]*$/u.test(value)) {
            return I18nFramework.Common_IncorrectValue();
        }

        return true;
    },

    /** Accepts only values listed in the arguments. */
    in_array: (value: string, args: readonly string[], _el: HTMLInputElement): string | boolean => {
        const v = value + '';

        for (let arg of args) {
            if (v === arg + '') {
                return true;
            }
        }

        return I18nFramework.Common_IncorrectValue();
    },

    /** Rejects an empty value. */
    required: (value: string, _args: readonly string[], _el: HTMLInputElement): string | boolean => {
        return value ? true : I18nFramework.Common_RequiredValue();
    },

    /** Accepts only browser-supported time zones. */
    tzExists: (value: string, _args: readonly string[], _el: HTMLInputElement): string | boolean => {
        const browserTimeZones = Intl.supportedValuesOf('timeZone');

        for (let tz of browserTimeZones) {
            if (tz === value) {
                return true;
            }
        }

        return I18nFramework.Common_IncorrectValue();
    },

    /** Accepts empty values or a basic email address. */
    email: (value: string, _args: readonly string[], _el: HTMLInputElement): string | boolean => {
        if (!value) {
            return true;
        }

        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        if (!emailRegex.test(value)) {
            return I18nFramework.Common_Email();
        }

        return true;
    },

    /** Accepts empty values or an integer. */
    int: (value: string, _args: readonly string[], _el: HTMLInputElement): string | boolean => {
        if (value === '' || value === null || value === undefined) {
            return true;
        }

        if (!Number.isInteger(Number(value))) {
            return I18nFramework.Common_Int();
        }

        return true;
    },

    /** Accepts values at least the configured minimum. */
    min: (value: string, args: readonly string[], _el: HTMLInputElement): string | boolean => {
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

    minVal: (value: string, args: readonly string[], _el: HTMLInputElement): string | boolean => {
        return Validators.min(value, args, _el);
    },

    /** Accepts values at most the configured maximum. */
    max: (value: string, args: readonly string[], _el: HTMLInputElement): string | boolean => {
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

    maxVal: (value: string, args: readonly string[], _el: HTMLInputElement): string | boolean => {
        return Validators.max(value, args, _el);
    },

    /** Accepts empty values or valid URLs. */
    url: (value: string, _args: readonly string[], _el: HTMLInputElement): string | boolean => {
        if (!value) {
            return true;
        }

        try {
            return Boolean(new URL(value));
        } catch {
            return I18nFramework.Common_Url();
        }
    },

    /** Accepts empty values or ASCII letters and digits. */
    alphanumeric: (value: string, _args: readonly string[], _el: HTMLInputElement): string | boolean => {
        if (!value) {
            return true;
        }

        if (!/^[a-zA-Z0-9]+$/.test(value)) {
            return I18nFramework.Common_Alphanumeric();
        }

        return true;
    },

    /** Requires the value to match the selected input. */
    match: (value: string, args: readonly string[], _el: HTMLInputElement): string | boolean => {
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

    /** Requires the value to satisfy a supplied regular expression. */
    pattern: (value: string, args: readonly string[], _el: HTMLInputElement): string | boolean => {
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
