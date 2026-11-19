/**
 * Поле загрузки изображения с обрезкой.
 */

import * as React from 'react';
import {AntiHookComponent} from '@react-carburetor/core';
import {TGridFieldInfo} from '@common/Dom/GridTable/Models';
import {I18nFramework} from '@framework/I18nGen/I18nFramework';
import {ICropData} from '@common/Support/Models';
import {DomEl} from '@common/Dom/El/DomEl';
import {componentUploadPhotoHandler} from '@common/Dom/Component/ComponentUploadPhotoHandler';
import {uploadMaxBytes, megabytes} from '@common/Utils/Upload/uploadLimits';
import {Upload, XCircle} from 'lucide-react';

type PhotoFieldProps = {
	column: string;
	value: unknown;
	fieldInfo: TGridFieldInfo;
	data: Record<string, unknown>;
	disabled: boolean;
	onFileChange: (column: string, file: Blob | null, cropInfo: ICropData | null) => void;
};

function photoPath(uploadPath: string | undefined, value: unknown, data: Record<string, unknown>): string | null {
	return !value ? null : (uploadPath || '').replace(/({(\w+)})/gi, (_, __, match) => {
		return (data[match] || 'null') as string;
	}) + value;
}

function renderPhotoControls(readOnly: boolean) {
	return (
		<div className="photo-controls-column">
			{!readOnly && (
				<>
					<button type="button" className="change-img-btn" title={I18nFramework.Action_Replace()}>
						<Upload size={18} />
					</button>
					<button type="button" className="del-img-btn" title={I18nFramework.Action_Del()}>
						<XCircle size={18} />
					</button>
				</>
			)}
		</div>
	);
}

export class PhotoField extends AntiHookComponent<PhotoFieldProps> {
	private readonly containerRef = React.createRef<HTMLDivElement>();
	private invalidateMount?: () => void;

	public componentDidMount(): void {
		super.componentDidMount();
		const el = this.containerRef.current;
		if (!el) return;

		let mounted = true;
		this.invalidateMount = () => { mounted = false; };
		const {column, value, fieldInfo, data, disabled, onFileChange} = this.props;
		const readOnly = fieldInfo.readOnly || disabled;
		const crop = !fieldInfo.cropInfo || !fieldInfo.cropName
			? false as const
			: data[fieldInfo.cropInfo] as undefined | {x: number; y: number; w: number; h: number};
		const photo = photoPath(fieldInfo.uploadPath, value, data);

		componentUploadPhotoHandler({
			readOnly: !!readOnly,
			visibilityMode: 'photo-data-attribute',
			crop,
			photoBlock: new DomEl(el),
			photo,
			onChange: (blobData: Blob | null, cropInfo: ICropData) => {
				if (mounted) onFileChange(column, blobData, crop !== false ? cropInfo : null);
			},
		});
	}

	public componentWillUnmount(): void {
		this.invalidateMount?.();
		super.componentWillUnmount();
	}

	public render() {
		const {value, fieldInfo, disabled} = this.props;
		const readOnly = fieldInfo.readOnly || disabled;
		if (!value && readOnly) return null;

		const maxBytes = uploadMaxBytes();
		const hint = readOnly || maxBytes <= 0
			? null
			: I18nFramework.Upload_ImageHint([megabytes(maxBytes)]);

		return (
			<div ref={this.containerRef} className={readOnly ? 'photo-field-readonly' : 'photo-field-area'} data-photo-readonly={readOnly ? 'true' : undefined}>
				<input type="file" className="input-file" data-photo-hidden="true" accept="image/png, image/jpeg, image/jpg, image/gif" />
				{!readOnly && (
					<button type="button" className="upload-img-btn" data-photo-hidden="true" title={I18nFramework.Action_Upload()}>
						<Upload size={18} />
					</button>
				)}
				<div className="crop-block" data-photo-hidden="true">
					<div className="photo-preview-box">
						<img className="image-src" alt="loading" src="data:," />
					</div>
					{renderPhotoControls(!!readOnly)}
				</div>
				{hint && (
					<p className="photo-upload-hint" data-test-id="photo-upload-hint">{hint}</p>
				)}
			</div>
		);
	}
}
