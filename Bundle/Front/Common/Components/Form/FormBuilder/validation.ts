/**
 * Разбор правил проверки поля и их применение к значению.
 *
 * Чистые функции: ни состояния, ни DOM, кроме переданного элемента —
 * поэтому их можно читать и менять, не держа в голове всю форму.
 */

import * as React from 'react';
import {useState, useRef, useEffect, useMemo, useCallback} from 'react';
import {IDetailsInfo, TGridFieldInfo, TGridSelectField, TValidationMapped} from '@common/Dom/GridTable/Models';
import {GridUtils} from '@common/Dom/GridTable/GridUtils';
import {printUtDateTime} from '@common/Utils/Str/printUtDateTime';
import {I18nFramework} from '@framework/I18nGen/I18nFramework';
import {IApiSuccessResponse, ICropData, IDataListItem, TFromMap, TFromNestedMap} from '@common/Support/Models';
import {sendPostFormData} from '@common/Api/Send/sendPostFormData';
import {makeFormData} from '@common/Api/Send/makeFormData';
import {Validators} from '@common/Dom/GridTable/Validators';
import {PageEvents} from '@common/Utils/Ui/PageEvents';
import {DomEl} from '@common/Dom/El/DomEl';
import {componentUploadPhotoHandler} from '@common/Dom/Component/ComponentUploadPhotoHandler';
import {uploadMaxBytes, megabytes} from '@common/Utils/Upload/uploadLimits';
import isString from 'lodash/isString';
import isObject from 'lodash/isObject';
import {Loader2} from 'lucide-react';
import {Upload, XCircle} from 'lucide-react';
import {UncontrolledForm, type UncontrolledFormHandle} from '../UncontrolledForm';
import {UncontrolledInput} from '../UncontrolledInput';
import {UncontrolledTextarea} from '../UncontrolledTextarea';
import {UncontrolledSelect} from '../UncontrolledSelect';


export const parseValidation = (el: string): TValidationMapped | null => {
	const params = el.match(/^(\w+)(\[(.+?)])?$/);
	const name = params?.[1];
	const args = params?.[3]?.split(',') || [];
	return name ? {name, args} : null;
};


export const runFieldValidation = (fieldInfo: TGridFieldInfo, value: string, inputEl?: HTMLInputElement): string | true => {
	const validators: TValidationMapped[] = (fieldInfo?.validation || [])
		.map((el) => {
			if (isString(el)) return el;
			if (isString((el as unknown[])?.[1])) return (el as unknown[])?.[1] as string;
			return null;
		})
		.filter(isString)
		.map(parseValidation)
		.filter((v): v is TValidationMapped => isObject(v));

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

// --- DatalistSelect (Combobox for large lists, native select for small) ---
import {Combobox} from '@common/Components/ui/Combobox';

