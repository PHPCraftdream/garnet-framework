import * as React from 'react';
import {AntiHookComponent} from '@react-carburetor/core';
import {TGridSelectField} from '@common/Dom/GridTable/Models';
import {GridUtils} from '@common/Dom/GridTable/GridUtils';
import {ICropData} from '@common/Support/Models';
import {FormBuilderProps} from './types';
import {FormBuilderInput} from './FormBuilderInput';
import {FormBuilderTextarea} from './FormBuilderTextarea';
import {FormBuilderSelect} from './FormBuilderSelect';
import {DatalistSelect} from './DatalistSelect';
import {PhotoField} from './PhotoField';
import {FormRow} from './FormRow';
import {FormBooleanField} from './FormBooleanField';
import {FormReadonlyValue} from './FormReadonlyValue';

interface Props {
	column: string;
	detailsInfo: FormBuilderProps['detailsInfo'];
	data: FormBuilderProps['data'];
	isNew: boolean;
	value: any;
	error?: string;
	submitting: boolean;
	inputRefs: React.MutableRefObject<Record<string, HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>>;
	onFieldChange: (column: string, value: any) => void;
	onPhotoChange: (column: string, file: Blob | null, cropInfo: ICropData | null) => void;
}

export class FormBuilderFieldRow extends AntiHookComponent<Props> {
	private readonly setInputRef = (el: HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement | null): void => {
		if (el) this.props.inputRefs.current[this.props.column] = el;
	};

	private readonly changeSelect = (value: string): void => {
		this.props.onFieldChange(this.props.column, value);
	};

	public render() {
		const {column, detailsInfo, isNew} = this.props;
		const fieldInfo = detailsInfo.fields[column];
		if (!fieldInfo || fieldInfo.hidden || (isNew && fieldInfo.readOnly && column !== detailsInfo.idColumn)) return null;
		return this.renderField(fieldInfo);
	}

	private renderField(fieldInfo: NonNullable<Props['detailsInfo']['fields'][string]>) {
		const {column, detailsInfo, data, value, error, submitting, inputRefs, onFieldChange, onPhotoChange} = this.props;
		const type = fieldInfo.type;
		const label = fieldInfo.name || '';

		if (type === 'photo') {
			if (!value && fieldInfo.readOnly) return null;
			return <FormRow label={label} error={error} align="center">
				<PhotoField column={column} value={value} fieldInfo={fieldInfo} data={data} disabled={submitting} onFileChange={onPhotoChange} />
			</FormRow>;
		}
		if (type === 'bool' || GridUtils.isBoolStr(type)) {
			return <FormRow label={' '} error={error}>
				<FormBooleanField column={column} label={label} checked={!!value || value === '1'}
					disabled={fieldInfo.readOnly || submitting} onChange={onFieldChange} inputRefs={inputRefs} />
			</FormRow>;
		}
		if (GridUtils.isTimeZone(type) || GridUtils.isSelect(type) || GridUtils.isMap(type)) {
			const selectArr = GridUtils.getSelectArr(type as TGridSelectField);
			if (selectArr?.length <= 10) {
				return <FormRow label={label}>
					<FormBuilderSelect name={column} defaultValue={value?.toString() || ''} options={selectArr}
						disabled={fieldInfo.readOnly || submitting} testId={`form-field-${column}`} ref={this.setInputRef} />
				</FormRow>;
			}
			return <FormRow label={label} error={error}>
				<DatalistSelect column={column} value={value?.toString() || ''} items={selectArr}
					disabled={fieldInfo.readOnly || submitting} onChange={this.changeSelect} />
			</FormRow>;
		}
		if (type === 'textarea') {
			return <FormRow label={label}>
				<FormBuilderTextarea name={column} defaultValue={value?.toString() || ''}
					disabled={fieldInfo.readOnly || submitting} rows={7} testId={`form-field-${column}`} ref={this.setInputRef} />
			</FormRow>;
		}
		if (fieldInfo.readOnly && column !== detailsInfo.idColumn) {
			return <FormRow label={label}><FormReadonlyValue value={value?.toString() || ''} /></FormRow>;
		}
		return <FormRow label={label}>
			<FormBuilderInput name={column} defaultValue={value?.toString() || ''}
				disabled={fieldInfo.readOnly || submitting} testId={`form-field-${column}`} ref={this.setInputRef} />
		</FormRow>;
	}
}
