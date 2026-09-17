/**
 * Строка формы: подпись, поле и место для сообщения об ошибке.
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
import {Loader2} from 'lucide-react';
import {Upload, XCircle} from 'lucide-react';
import {UncontrolledForm, type UncontrolledFormHandle} from '../UncontrolledForm';
import {UncontrolledInput} from '../UncontrolledInput';
import {UncontrolledTextarea} from '../UncontrolledTextarea';
import {UncontrolledSelect} from '../UncontrolledSelect';


export const FormRow: React.FC<{label: string; error?: string; align?: 'start' | 'center'; children: React.ReactNode}> = ({label, error, align = 'start', children}) => (
	<div className={`grid sm:grid-cols-12 gap-x-3 mb-4 ${align === 'center' ? 'items-center' : 'items-start'}`}>
		<label className="sm:col-span-2 col-form-label main-label">{label || '\u00A0'}</label>
		<div className="sm:col-span-10">
			{children}
			{error && <div className="garnet-form-error small fs-7 text-danger">{error}</div>}
		</div>
	</div>
);

// --- Main FormBuilder ---
