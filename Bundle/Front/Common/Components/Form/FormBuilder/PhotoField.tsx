/**
 * Поле загрузки изображения с обрезкой.
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


export const PhotoField: React.FC<{
	column: string;
	value: unknown;
	fieldInfo: TGridFieldInfo;
	data: Record<string, unknown>;
	disabled: boolean;
	onFileChange: (column: string, file: Blob | null, cropInfo: ICropData | null) => void;
}> = ({column, value, fieldInfo, data, disabled, onFileChange}) => {
	const containerRef = useRef<HTMLDivElement>(null);
	const readOnly = fieldInfo.readOnly || disabled;

	useEffect(() => {
		const el = containerRef.current;
		if (!el) return;

		const photoBlock = new DomEl(el);

		const crop = (() => {
			if (!fieldInfo.cropInfo || !fieldInfo.cropName) return false as false;
			return data[fieldInfo.cropInfo] as undefined | {x: number; y: number; w: number; h: number};
		})();

		const photo = (() => {
			if (!value) return null;
			return (fieldInfo?.uploadPath || '').replace(/({(\w+)})/gi, (_, __, match) => {
				return (data[match] || 'null') as string;
			}) + value;
		})();

		componentUploadPhotoHandler({
			readOnly: !!readOnly,
			crop,
			photoBlock,
			photo,
			onChange: (blobData: Blob | null, cropInfo: ICropData) => {
				onFileChange(column, blobData, crop !== false ? cropInfo : null);
			},
		});
	}, []);

	if (!value && readOnly) return null;

	// Limits belong next to the control, before a file is chosen. Two people
	// reported independently that the only way to learn them was to be
	// refused — and for a while being refused destroyed the photo instead.
	const maxBytes = uploadMaxBytes();
	const hint = readOnly || maxBytes <= 0
		? null
		: I18nFramework.Upload_ImageHint([megabytes(maxBytes)]);

	return (
		<div ref={containerRef} className={readOnly ? 'pointer-disabled' : ''}>
			<input type="file" className="form-control input-file d-none" accept="image/png, image/jpeg, image/jpg, image/gif" />
			{!readOnly && (
				<button type="button" className="btn btn-light upload-img-btn d-none" title={I18nFramework.Action_Upload()}>
					<Upload size={18} />
				</button>
			)}
			<div className="crop-block d-none p-3 flex flex-row">
				<div className="d" style={{maxWidth: '400px', maxHeight: '300px'}}>
					<img className="image-src max-w-full h-auto" alt="loading" src="data:," />
				</div>
				<div className="px-2 flex flex-col">
					{!readOnly && (
						<>
							<button type="button" className="mb-2 btn btn-light change-img-btn" title={I18nFramework.Action_Replace()}>
								<Upload size={18} />
							</button>
							<button type="button" className="btn btn-light del-img-btn" title={I18nFramework.Action_Del()}>
								<XCircle size={18} />
							</button>
						</>
					)}
				</div>
			</div>
			{hint && (
				<p className="text-xs text-muted mt-1" data-test-id="photo-upload-hint">{hint}</p>
			)}
		</div>
	);
};

// --- FormRow ---
