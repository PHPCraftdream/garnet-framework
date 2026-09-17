/**
 * Поле с подсказками из готового списка значений.
 */

import * as React from 'react';
import {useState, useRef, useEffect, useMemo, useCallback} from 'react';
import {IDetailsInfo, TGridFieldInfo, TGridSelectField, TValidationMapped} from '@common/Dom/GridTable/Models';
import {GridUtils} from '@common/Dom/GridTable/GridUtils';
import {printUtDateTime} from '@common/Utils/Str/printUtDateTime';
import {I18nFramework} from '@framework/I18nGen/I18nFramework';
import {IApiSuccessResponse, ICropData, IDataListItem, TFromMap, TFromNestedMap} from '@common/Models';
import {sendPostFormData} from '@common/Api/sendPostFormData';
import {makeFormData} from '@common/Api/makeFormData';
import {Validators} from '@common/Dom/GridTable/Validators';
import {PageEvents} from '@common/Utils/PageEvents';
import {DomEl} from '@common/Dom/DomEl';
import {componentUploadPhotoHandler} from '@common/Dom/ComponentUploadPhotoHandler';
import {uploadMaxBytes, megabytes} from '@common/Utils/Upload/uploadLimits';
import isString from 'lodash/isString';
import isObject from 'lodash/isObject';
import {Loader2} from 'lucide-react';
import {Upload, XCircle} from 'lucide-react';
import {UncontrolledForm, type UncontrolledFormHandle} from '../UncontrolledForm';
import {UncontrolledInput} from '../UncontrolledInput';
import {UncontrolledTextarea} from '../UncontrolledTextarea';
import {UncontrolledSelect} from '../UncontrolledSelect';
import {Combobox} from '@common/Components/ui/Combobox';


export const DatalistSelect: React.FC<{
	column: string;
	value: string;
	items: IDataListItem[];
	disabled: boolean;
	onChange: (value: string) => void;
}> = ({column, value, items, disabled, onChange}) => {
	// Use searchable Combobox for large option lists (>10 items, e.g. timezones)
	if (items.length > 10) {
		return (
			<Combobox
				options={items.map(item => ({value: String(item.value), label: item.text}))}
				value={String(value)}
				onChange={onChange}
				placeholder="..."
				searchPlaceholder="Search..."
				emptyText="—"
				testId={`form-field-${column}`}
			/>
		);
	}

	// Native select for small lists
	return (
		<select
			className="form-select"
			value={value}
			disabled={disabled}
			onChange={(e) => onChange(e.target.value)}
			data-test-id={`form-field-${column}`}
		>
			{items.map((item) => (
				<option key={item.value} value={item.value}>
					{item.text}
				</option>
			))}
		</select>
	);
};

// --- PhotoField ---

