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
import isString from 'lodash/isString';
import isObject from 'lodash/isObject';
import {Loader2} from 'lucide-react';
import {Upload, XCircle} from 'lucide-react';
import {UncontrolledForm, type UncontrolledFormHandle} from './UncontrolledForm';
import {UncontrolledInput} from './UncontrolledInput';
import {UncontrolledTextarea} from './UncontrolledTextarea';
import {UncontrolledSelect} from './UncontrolledSelect';

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

const parseValidation = (el: string): TValidationMapped | null => {
	const params = el.match(/^(\w+)(\[(.+?)])?$/);
	const name = params?.[1];
	const args = params?.[3]?.split(',') || [];
	return name ? {name, args} : null;
};

const runFieldValidation = (fieldInfo: TGridFieldInfo, value: string, inputEl?: HTMLInputElement): string | true => {
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

const DatalistSelect: React.FC<{
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

const PhotoField: React.FC<{
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
		</div>
	);
};

// --- FormRow ---

const FormRow: React.FC<{label: string; error?: string; align?: 'start' | 'center'; children: React.ReactNode}> = ({label, error, align = 'start', children}) => (
	<div className={`grid sm:grid-cols-12 gap-x-3 mb-4 ${align === 'center' ? 'items-center' : 'items-start'}`}>
		<label className="sm:col-span-2 col-form-label main-label">{label || '\u00A0'}</label>
		<div className="sm:col-span-10">
			{children}
			{error && <div className="garnet-form-error small fs-7 text-danger">{error}</div>}
		</div>
	</div>
);

// --- Main FormBuilder ---

export const FormBuilder: React.FC<FormBuilderProps> = ({
	detailsInfo,
	data,
	isNew = false,
	defaultValues = {},
	onSuccess,
	onCancel,
	onFail,
	footer,
	beforeSubmit,
}) => {
	const [values, setValues] = useState<Record<string, any>>(() => {
		const initial: Record<string, any> = {};
		for (const column of detailsInfo.detailsFields) {
			const fieldInfo = detailsInfo.fields[column];
			if (!fieldInfo || fieldInfo.hidden) continue;
			if (isNew && fieldInfo.readOnly && column !== detailsInfo.idColumn) continue;
			const type = fieldInfo.type;
			let val = type === 'unix_time' ? printUtDateTime(data[column] + '') : data[column];

			// For select fields with no initial value, default to first option (matches browser behavior)
			if (!val && (GridUtils.isTimeZone(type) || GridUtils.isSelect(type) || GridUtils.isMap(type))) {
				const selectArr = GridUtils.getSelectArr(type as TGridSelectField);
				if (selectArr?.length > 0) {
					val = selectArr[0].value;
				}
			}

			initial[column] = val ?? '';
		}
		return initial;
	});

	const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
	const [commonErrors, setCommonErrors] = useState<string[]>([]);
	const [submitting, setSubmitting] = useState(false);
	const [progress, setProgress] = useState<number | null>(null);

	const photoDataRef = useRef<Record<string, Blob | null>>({});
	const cropInfoRef = useRef<Record<string, ICropData | null>>({});
	const inputRefs = useRef<Record<string, HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>>({});
	const formRef = useRef<UncontrolledFormHandle>(null);

	const saveUrl = useMemo(() => {
		if (isNew) {
			return detailsInfo.saveUrl?.replace('~save_', '~create_') ?? detailsInfo.saveUrl ?? '';
		}
		return detailsInfo.saveUrl ?? '';
	}, [detailsInfo.saveUrl, isNew]);

	const handleFieldChange = useCallback((column: string, value: any) => {
		setValues(prev => ({...prev, [column]: value}));
		setFieldErrors(prev => {
			if (!prev[column]) return prev;
			const next = {...prev};
			delete next[column];
			return next;
		});
	}, []);

	const handlePhotoChange = useCallback((column: string, file: Blob | null, cropInfo: ICropData | null) => {
		photoDataRef.current[column] = file;
		cropInfoRef.current[column] = cropInfo;
	}, []);

	const handleSubmit = useCallback((formEl: HTMLFormElement | undefined) => {
		if (beforeSubmit && !beforeSubmit()) {
			return;
		}

		const fd = formEl ? new FormData(formEl) : null;
		const formValues: Record<string, string> = {};
		if (fd) fd.forEach((v, k) => { if (typeof v === 'string') formValues[k] = v; });

		const resultObj: TFromMap = {...defaultValues};
		const errors: Record<string, string> = {};
		let valid = true;

		for (const column of detailsInfo.detailsFields) {
			const fieldInfo = detailsInfo.fields[column];
			if (fieldInfo?.hidden) {
				resultObj[column] = data[column] as string;
			}
		}

		for (const column of detailsInfo.detailsFields) {
			const fieldInfo = detailsInfo.fields[column];
			if (!fieldInfo || fieldInfo.hidden) continue;
			if (isNew && fieldInfo.readOnly && column !== detailsInfo.idColumn) continue;

			const type = fieldInfo.type;

			if (fieldInfo.readOnly && column !== detailsInfo.idColumn && type !== 'photo') continue;

			if (type === 'photo') {
				if (fieldInfo.readOnly) continue;
				const file = photoDataRef.current[column];
				const ci = cropInfoRef.current[column];
				if (fieldInfo.cropInfo && ci) {
					resultObj[fieldInfo.cropInfo] = ci as unknown as TFromNestedMap;
				}
				resultObj[column] = file || (values[column] as string) || null;
				continue;
			}

			// For uncontrolled fields, prefer FormData value; fall back to values state
			// Uncontrolled: textarea, text inputs (default), unix_time, small selects
			// Controlled: bool/checkbox, Combobox (>10 items), photo
			const selectArr = (GridUtils.isTimeZone(type) || GridUtils.isSelect(type) || GridUtils.isMap(type))
				? GridUtils.getSelectArr(type as TGridSelectField)
				: null;
			const isSelectSmallList = selectArr != null && selectArr.length <= 10;
			const isUncontrolled = type === 'textarea'
				|| type === 'unix_time'
				|| isSelectSmallList
				|| (
					type !== 'bool' && !GridUtils.isBoolStr(type)
					// `type === 'photo'` is handled and `continue`d above, so the
					// type is already narrowed to exclude 'photo' here.
					&& !selectArr // not a select at all → plain text input
				);
			const value = isUncontrolled && formValues[column] !== undefined
				? formValues[column]
				: values[column];
			const inputEl = inputRefs.current[column] as HTMLInputElement;

			const strValue =
				type === 'bool' || GridUtils.isBoolStr(type) ? (value ? '1' : '0') : (value?.toString() || '');

			const validResult = runFieldValidation(fieldInfo, strValue, inputEl);
			if (validResult !== true) {
				errors[column] = validResult;
				valid = false;
			}

			if (type === 'bool' || GridUtils.isBoolStr(type)) {
				resultObj[column] = value ? 1 : 0;
			} else {
				resultObj[column] = value || null;
			}
		}

		if (!valid) {
			setFieldErrors(errors);
			formRef.current?.setFieldErrors(errors);
			setCommonErrors([I18nFramework.Common_FromHasError()]);
			return;
		}

		setFieldErrors({});
		formRef.current?.setFieldErrors({});
		setCommonErrors([]);
		setSubmitting(true);

		const formData = makeFormData(resultObj);

		sendPostFormData(saveUrl, formData, (p) => setProgress(p))
			.then((result: any) => {
				setProgress(null);

				if (result?.errors || result?.commonErrors) {
					setSubmitting(false);
					const backendErrors: Record<string, string> = {};
					if (result.errors && typeof result.errors === 'object') {
						for (const [field, errs] of Object.entries(result.errors)) {
							if (Array.isArray(errs)) {
								backendErrors[field] = (errs as string[])[0];
							} else if (typeof errs === 'string') {
								backendErrors[field] = errs;
							}
						}
					}
					setFieldErrors(backendErrors);
					formRef.current?.setFieldErrors(backendErrors);

					const ce: string[] = [];
					if (Array.isArray(result.commonErrors)) {
						ce.push(...result.commonErrors);
					} else if (typeof result.commonErrors === 'string') {
						ce.push(result.commonErrors);
					}
					if (Object.keys(backendErrors).length > 0) {
						ce.push(I18nFramework.Common_FromHasError());
					}
					setCommonErrors(ce);
					return;
				}

				if (!result?.ok) {
					setSubmitting(false);
					setCommonErrors([I18nFramework.Common_RequestError()]);
					return;
				}

				// Success — keep the button locked with its loader; the page
				// navigates away in onSuccess. Only failures re-enable it.
				onSuccess(result);
			})
			.catch((e: Error) => {
				setSubmitting(false);
				setProgress(null);
				setCommonErrors([I18nFramework.Common_RequestError()]);
				onFail?.();
				throw e;
			});
	}, [values, detailsInfo, data, isNew, defaultValues, saveUrl, onSuccess, onFail, beforeSubmit]);

	const buttonText = isNew ? (I18nFramework.Common_Create?.() || 'Create') : I18nFramework.Common_Save();

	return (
		<UncontrolledForm ref={formRef} onSubmit={(_values, form) => handleSubmit(form)}>
		{detailsInfo.detailsFields.map((column) => {
			const fieldInfo = detailsInfo.fields[column];
			if (!fieldInfo) return null;
			if (fieldInfo.hidden) return null;
			if (isNew && fieldInfo.readOnly && column !== detailsInfo.idColumn) return null;

			const type = fieldInfo.type;
			const label = fieldInfo.name || '';
			const value = values[column];
			const error = fieldErrors[column];

			if (type === 'photo') {
				if (!value && fieldInfo.readOnly) return null;
				return (
					<FormRow key={column} label={label} error={error} align="center">
						<PhotoField
							column={column}
							value={value}
							fieldInfo={fieldInfo}
							data={data}
							disabled={submitting}
							onFileChange={handlePhotoChange}
						/>
					</FormRow>
				);
			}

			if (type === 'bool' || GridUtils.isBoolStr(type)) {
				return (
					<FormRow key={column} label={'\u00A0'} error={error}>
						<div className="form-check form-switch">
							<input
								type="checkbox"
								className="form-check-input"
								checked={!!value || value === '1'}
								disabled={fieldInfo.readOnly || submitting}
								onChange={(e) => handleFieldChange(column, e.target.checked)}
								ref={(el) => { if (el) inputRefs.current[column] = el; }}
								data-test-id={`form-field-${column}`}
							/>
							<label className="form-check-label">{label}</label>
						</div>
					</FormRow>
				);
			}

			if (GridUtils.isTimeZone(type) || GridUtils.isSelect(type) || GridUtils.isMap(type)) {
				const selectArr = GridUtils.getSelectArr(type as TGridSelectField);
				if (selectArr?.length <= 10) {
					return (
						<FormRow key={column} label={label}>
							<UncontrolledSelect
								name={column}
								defaultValue={value?.toString() || ''}
								options={selectArr}
								disabled={fieldInfo.readOnly || submitting}
								testId={`form-field-${column}`}
								ref={(el) => { if (el) inputRefs.current[column] = el; }}
							/>
						</FormRow>
					);
				}

				return (
					<FormRow key={column} label={label} error={error}>
						<DatalistSelect
							column={column}
							value={value?.toString() || ''}
							items={selectArr}
							disabled={fieldInfo.readOnly || submitting}
							onChange={(val) => handleFieldChange(column, val)}
						/>
					</FormRow>
				);
			}

			if (type === 'textarea') {
				return (
					<FormRow key={column} label={label}>
						<UncontrolledTextarea
							name={column}
							defaultValue={value?.toString() || ''}
							disabled={fieldInfo.readOnly || submitting}
							rows={7}
							testId={`form-field-${column}`}
							ref={(el) => { if (el) inputRefs.current[column] = el; }}
						/>
					</FormRow>
				);
			}

			if (fieldInfo.readOnly && column !== detailsInfo.idColumn) {
				return (
					<FormRow key={column} label={label}>
						<div className="col-form-label">{value?.toString() || ''}</div>
					</FormRow>
				);
			}

			return (
				<FormRow key={column} label={label}>
					<UncontrolledInput
						name={column}
						defaultValue={value?.toString() || ''}
						disabled={fieldInfo.readOnly || submitting}
						testId={`form-field-${column}`}
						ref={(el) => { if (el) inputRefs.current[column] = el; }}
					/>
				</FormRow>
			);
		})}

			{footer}

			<FormRow label={'\u00A0'}>
				{commonErrors.length > 0 && (
					<div className="common-errors">
						{commonErrors.map((err, i) => (
							<div key={i} className="alert alert-danger mb-2" role="alert">
								{err}
							</div>
						))}
					</div>
				)}
				{progress !== null && (
					<div className="reg-progress progress mb-2">
						<div className="progress-bar" style={{width: `${progress}%`}} />
					</div>
				)}
				{onCancel && (
					<button
						type="button"
						className="btn btn-outline-secondary save-btn-cancel"
						onClick={onCancel}
						disabled={submitting}
					>
						{I18nFramework.Common_Cancel()}
					</button>
				)}
				<button
					type="submit"
					className="btn btn-primary save-btn-submit mx-2 inline-flex items-center gap-1.5"
					disabled={submitting}
					data-test-id="form-save-btn"
				>
					{submitting && <Loader2 size={16} className="animate-spin" aria-hidden="true" />}
					{buttonText}
				</button>
			</FormRow>
		</UncontrolledForm>
	);
};
