/**
 * Пропсы конструктора форм.
 *
 * Отдельным файлом потому, что их импортирует GridTable — он собирает
 * форму через React.createElement и типизирует набор пропсов, не трогая
 * саму реализацию.
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


export interface FormBuilderProps {
	detailsInfo: IDetailsInfo;
	data: Record<string, unknown>;
	isNew?: boolean;
	defaultValues?: Record<string, string | number>;
	onSuccess: (data: IApiSuccessResponse) => void;
	onCancel?: () => void;
	onFail?: () => void;
	/** Extra content rendered INSIDE the form, just above the submit row. */
	footer?: React.ReactNode;
	/** Called before submit; return false to block submission (caller shows its own error). */
	beforeSubmit?: () => boolean;
}

// --- Validation ---
