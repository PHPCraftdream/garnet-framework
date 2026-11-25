/**
 * Разбор правил проверки поля и их применение к значению.
 *
 * Чистые функции: ни состояния, ни DOM, кроме переданного элемента —
 * поэтому их можно читать и менять, не держа в голове всю форму.
 */

import {TGridFieldInfo, TValidationMapped} from '@common/Dom/GridTable/Models';
import {Validators} from '@common/Dom/GridTable/Validators';
import {PageEvents} from '@common/Utils/Ui/PageEvents';


/** Handles this operation. */
import {parseValidation} from './parseValidation';

const isString = (value: unknown): value is string => typeof value === 'string';
/** Handles this operation. */
function readRule(entry: unknown): string | null {
	if (isString(entry)) return entry;
	const rule = (entry as unknown[])?.[1];
	return isString(rule) ? rule : null;
}

/** Handles this operation. */
function isParsedRule(rule: TValidationMapped | null): rule is TValidationMapped {
	return rule !== null;
}

/** Handles this operation. */
export const runFieldValidation = (fieldInfo: TGridFieldInfo, value: string, inputEl?: HTMLInputElement):
    string | true => {
	const validators = (fieldInfo?.validation || [])
		.map(readRule)
		.filter(isString)
		.map(parseValidation)
		.filter(isParsedRule);

	for (const validator of validators) {
		if ((Validators as any)[validator.name]) {
			const result = (Validators as any)[validator.name](value, validator.args, inputEl);
			if (result !== true) return result as string;
			continue;
		}

		const eventObj = {info: validator, value, result: true as boolean | string, el: inputEl};
		PageEvents.init().emmit(`validate_${validator.name}`, eventObj);
		if (eventObj.result !== true) return eventObj.result as string;
	}

	return true;
};
