import {TFormFileValue, TFromMap, TFromValue} from '@common/Models';

type TResultItem = {name: string, value: number | string | Blob | File};
type TResult = TResultItem[];
type TObjToFate = TFromValue | TFromMap | TFormFileValue | null | undefined;

/**
 * @param obj
 * @param name
 * @param result
 */
const makeFormFlatArr = (obj: TObjToFate, name: string = '', result: TResult = []): TResult => {
    if (obj === null || obj === undefined) {
        return result;
    }

    if (typeof obj === 'number' || typeof obj === 'string' || obj instanceof Blob || obj instanceof File) {
        result.push({name, value: obj});

        return result;
    }

    if (Array.isArray(obj)) {
        for (let [i, v] of Object.entries(obj)) {
            makeFormFlatArr(v, name + `[${i}]`, result);
        }

        return result;
    }

    for (const [key, value] of Object.entries(obj)) {
        const propName = name ? `${name}[${key}]` : key;
        makeFormFlatArr(value, propName, result);
    }

    return result;
};

export const makeFormData = (obj: TFromMap): FormData => {
    const flatArr = makeFormFlatArr(obj);
    const result = new FormData();

    for (let item of flatArr) {
        if ((item.value instanceof File) || (item.value instanceof Blob)) {
            result.append(item.name, item.value, item.name);
        } else {
            result.append(item.name, item.value as string);
        }
    }

    return result;
};
