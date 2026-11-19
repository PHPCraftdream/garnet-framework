/**
 * Конструктор форм: собирает поля по описанию сетки и отправляет их.
 *
 * Файл называется index, чтобы путь импорта остался прежним —
 * @common/Components/Form/FormBuilder используют и GridTable, и форма
 * регистрации приложения, причём GridTable импортирует ещё и тип пропсов.
 */

import * as React from 'react';
import {AntiHookComponent, Carburetor, type TReadonly} from '@react-carburetor/core';
import {TGridSelectField} from '@common/Dom/GridTable/Models';
import {GridUtils} from '@common/Dom/GridTable/GridUtils';
import {printUtDateTime} from '@common/Utils/Str/printUtDateTime';
import {I18nFramework} from '@framework/I18nGen/I18nFramework';
import {ICropData, TFromMap, TFromNestedMap} from '@common/Support/Models';
import {sendPostFormData} from '@common/Api/Send/sendPostFormData';
import {makeFormData} from '@common/Api/Send/makeFormData';
import {Loader2} from 'lucide-react';
import {UncontrolledForm, type UncontrolledFormHandle} from '../UncontrolledForm';
import {FormBuilderProps} from './types';
import {runFieldValidation} from './validation';
import {FormRow} from './FormRow';
import {FormBuilderFieldRow} from './FormBuilderFieldRow';

export type {FormBuilderProps};

const EMPTY_DEFAULT_VALUES: Record<string, string | number> = {};

type TrackedFieldValue = string | number | boolean | bigint | symbol | null | undefined;

interface FormBuilderStatus {
	values: Record<string, TrackedFieldValue>;
	valuesRevision: number;
	fieldErrors: Record<string, string>;
	commonErrors: string[];
	submitting: boolean;
	progress: number | null;
}

class FormBuilderStatusState {
	private opaqueValues = new Map<string, unknown>();
	private cachedValues: Record<string, any> | undefined;
	private cachedRevision = -1;
	public readonly store: Carburetor<FormBuilderStatus>;

	public constructor(initial: Record<string, any>) {
		const {tracked, opaque} = this.splitValues(initial);
		this.opaqueValues = opaque;
		this.store = new Carburetor<FormBuilderStatus>({
			values: tracked, valuesRevision: 0,
			fieldErrors: {}, commonErrors: [], submitting: false, progress: null,
		});
	}

	private splitValues(values: Record<string, any>): {
		tracked: Record<string, TrackedFieldValue>;
		opaque: Map<string, unknown>;
	} {
		const tracked: Record<string, TrackedFieldValue> = {};
		const opaque = new Map<string, unknown>();
		for (const [key, value] of Object.entries(values)) {
			const isOpaque = value !== null && (typeof value === 'object' || typeof value === 'function');
			if (isOpaque) opaque.set(key, value);
			Object.defineProperty(tracked, key, {value: isOpaque ? null : value, enumerable: true, writable: true, configurable: true});
		}
		return {tracked, opaque};
	}

	public readonly getValues = (): Record<string, any> => {
		const state = this.store.getData();
		if (this.cachedValues && this.cachedRevision === state.valuesRevision) return this.cachedValues;
		const values: Record<string, any> = {};
		for (const key of Object.keys(state.values)) {
			const value = this.opaqueValues.has(key) ? this.opaqueValues.get(key) : state.values[key];
			Object.defineProperty(values, key, {value, enumerable: true, writable: true, configurable: true});
		}
		this.cachedRevision = state.valuesRevision;
		this.cachedValues = values;
		return values;
	};

	public readonly setValues: React.Dispatch<React.SetStateAction<Record<string, any>>> = update => {
		const previous = this.getValues();
		const values = typeof update === 'function' ? update(previous) : update;
		if (Object.is(values, previous)) return;
		const {tracked, opaque} = this.splitValues(values);
		const state = this.store.getData();
		this.opaqueValues = opaque;
		this.cachedValues = undefined;
		this.store.setData({...state, values: tracked, valuesRevision: state.valuesRevision + 1});
	};

	public readonly setFieldErrors: React.Dispatch<React.SetStateAction<Record<string, string>>> = update => {
		const state = this.store.getData();
		const fieldErrors = typeof update === 'function' ? update(state.fieldErrors) : update;
		if (fieldErrors !== state.fieldErrors) this.store.setData({...state, fieldErrors});
	};

	public readonly setCommonErrors = (commonErrors: string[]): void => {
		this.store.setData({...this.store.getData(), commonErrors});
	};

	public readonly setSubmitting = (submitting: boolean): void => {
		this.store.setData({...this.store.getData(), submitting});
	};

	public readonly setProgress = (progress: number | null): void => {
		this.store.setData({...this.store.getData(), progress});
	};
}

type FormBuilderViewProps = FormBuilderProps & {status: FormBuilderStatusState};
type FormBuilderBodyProps = FormBuilderViewProps & {snapshot: TReadonly<FormBuilderStatus>};

class FormBuilderView extends AntiHookComponent<FormBuilderViewProps> {
	private readonly snapshot = this.connectSelection(() => this.props.status.store, state => state);

	public render() {
		return <FormBuilderBody {...this.props} snapshot={this.snapshot()} />;
	}
}

function initialFormValues({detailsInfo, data, isNew = false}: FormBuilderProps): Record<string, any> {
	const initial: Record<string, any> = {};
	for (const column of detailsInfo.detailsFields) {
		const fieldInfo = detailsInfo.fields[column];
		if (!fieldInfo || fieldInfo.hidden) continue;
		if (isNew && fieldInfo.readOnly && column !== detailsInfo.idColumn) continue;
		const type = fieldInfo.type;
		let val = type === 'unix_time' ? printUtDateTime(data[column] + '') : data[column];

		if (!val && (GridUtils.isTimeZone(type) || GridUtils.isSelect(type) || GridUtils.isMap(type))) {
			const selectArr = GridUtils.getSelectArr(type as TGridSelectField);
			if (selectArr?.length > 0) val = selectArr[0].value;
		}
		initial[column] = val ?? '';
	}
	return initial;
}

export class FormBuilder extends AntiHookComponent<FormBuilderProps> {
	private readonly status = new FormBuilderStatusState(initialFormValues(this.props));

	public render() {
		return <FormBuilderView {...this.props} status={this.status} />;
	}
}

interface FormBuilderStatusPanelProps {
	commonErrors: readonly string[];
	progress: number | null;
	submitting: boolean;
	onCancel?: () => void;
	buttonText: string;
}

function renderCommonErrors(errors: readonly string[]): React.ReactNode[] {
	const counts = new Map<string, number>();
	const nodes: React.ReactNode[] = [];
	for (const error of errors) {
		const occurrence = counts.get(error) ?? 0;
		counts.set(error, occurrence + 1);
		nodes.push(<div key={JSON.stringify([error, occurrence])} className="form-builder-common-error" role="alert">{error}</div>);
	}
	return nodes;
}

function FormBuilderStatusPanel({commonErrors, progress, submitting, onCancel, buttonText}: FormBuilderStatusPanelProps) {
	return (
		<FormRow label={' '}>
			{commonErrors.length > 0 && (
				<div className="common-errors">
					{renderCommonErrors(commonErrors)}
				</div>
			)}
			{progress !== null && (
				<div className="reg-progress">
					<div className="progress-bar" style={{width: `${progress}%`}} />
				</div>
			)}
			{onCancel && (
				<button type="button" className="save-btn-cancel" onClick={onCancel} disabled={submitting}>
					{I18nFramework.Common_Cancel()}
				</button>
			)}
			<button
				type="submit"
				className="save-btn-submit"
				disabled={submitting}
				data-test-id="form-save-btn"
			>
				{submitting && <Loader2 size={16} className="form-builder-spinner" aria-hidden="true" />}
				{buttonText}
			</button>
		</FormRow>
	);
}

class FormBuilderBody extends AntiHookComponent<FormBuilderBodyProps> {
	private readonly photoDataRef = {current: {} as Record<string, Blob | null>};
	private readonly cropInfoRef = {current: {} as Record<string, ICropData | null>};
	private readonly inputRefs = {current: {} as Record<string, HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>};
	private readonly formRef = React.createRef<UncontrolledFormHandle>();

	private readonly handleFieldChange = (column: string, value: any) => {
		const {setValues, setFieldErrors} = this.props.status;
		setValues(prev => ({...prev, [column]: value}));
		setFieldErrors(prev => {
			if (!prev[column]) return prev;
			const next = {...prev};
			delete next[column];
			return next;
		});
	};

	private readonly handlePhotoChange = (column: string, file: Blob | null, cropInfo: ICropData | null) => {
		const {photoDataRef, cropInfoRef} = this;
		photoDataRef.current[column] = file;
		cropInfoRef.current[column] = cropInfo;
	};

	private readonly handleSubmit = (formEl: HTMLFormElement | undefined) => {
		const {status, detailsInfo, data, isNew = false, defaultValues = EMPTY_DEFAULT_VALUES, beforeSubmit, onSuccess, onFail} = this.props;
		const {setFieldErrors, setCommonErrors, setSubmitting, setProgress} = status;
		const values = status.getValues();
		const {formRef, photoDataRef, cropInfoRef, inputRefs} = this;
		const configuredUrl = detailsInfo.saveUrl ?? '';
		const saveUrl = isNew ? configuredUrl.replace('~save_', '~create_') : configuredUrl;
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
	};

	private readonly handleFormSubmit = (_values: Record<string, string>, form: HTMLFormElement) => this.handleSubmit(form);

	private readonly renderField = (column: string) => {
		const {detailsInfo, data, isNew = false, status, snapshot} = this.props;
		return <FormBuilderFieldRow key={column} column={column} detailsInfo={detailsInfo} data={data}
			isNew={isNew} value={status.getValues()[column]} error={snapshot.fieldErrors[column]} submitting={snapshot.submitting}
			inputRefs={this.inputRefs} onFieldChange={this.handleFieldChange} onPhotoChange={this.handlePhotoChange} />;
	};

	render() {
		const {detailsInfo, snapshot, isNew = false, onCancel, footer} = this.props;
		const {commonErrors, progress, submitting} = snapshot;
		const buttonText = isNew ? (I18nFramework.Common_Create?.() || 'Create') : I18nFramework.Common_Save();
		return (
			<UncontrolledForm ref={this.formRef} onSubmit={this.handleFormSubmit}>
				{detailsInfo.detailsFields.map(this.renderField)}
				{footer}
				<FormBuilderStatusPanel commonErrors={commonErrors} progress={progress} submitting={submitting}
					onCancel={onCancel} buttonText={buttonText} />
			</UncontrolledForm>
		);
	}
}
