import * as React from 'react';
import {createRoot, Root} from 'react-dom/client';
import {Component} from '@common/Dom/Component';
import {parseJson} from '@common/Utils/Str/parseJson';
import {decode} from 'js-base64';
import {IGridInfo, TGridFieldType} from '@common/Dom/GridTable/Models';
import {GridUtils} from '@common/Dom/GridTable/GridUtils';
import {TColumn} from 'gridjs/dist/src/types';
import {printUtDateTime} from '@common/Utils/Str/printUtDateTime';
import {Grid, html} from 'gridjs';
import 'gridjs/dist/theme/mermaid.min.css';
import escape from 'lodash/escape';
import {gridConfig} from '@common/Dom/GridTable/GridConfig';
import {classClick} from '@common/Dom/ClassClick';
import {DomEl} from '@common/Dom/DomEl';
import {FormBuilder, FormBuilderProps} from '@common/Components/Form/FormBuilder';
import {sendPost} from '@common/Api/sendPost';

declare global {
	interface Window {
		__GARNET_CSRF__?: string;
	}
}
import {IApiSuccessResponse} from '@common/Models';
import {I18nFramework} from '@framework/I18nGen/I18nFramework';
import {iconSvg} from '@common/Utils/LucideIcons';

export class GridTable extends Component {
	protected grid!: Grid;
	protected formRoot: Root | null = null;
	protected gridInfo: IGridInfo | null = null;
	protected gridContainer: DomEl<HTMLElement> | null = null;
	protected editContainer: DomEl<HTMLElement> | null = null;
	protected headerContainer: DomEl<HTMLElement> | null = null;

	protected dataMap: Record<string, Record<string, unknown>> = {};
	protected columnsMap: Record<string, {name: string; type: TGridFieldType}> = {};
	protected isNewItem: boolean = false;

	init = () => {
		const main = this;

		this.editContainer = main.get('.edit-container');
		this.gridContainer = main.get('.grid-container');
		this.headerContainer = main.get('.header-container');

		this.get('.grid-info')?.then((domEl) => {
			const obj = decode(domEl.text());
			const gridInfo = parseJson<IGridInfo>(obj);

			if (!GridUtils.isGridData(gridInfo)) {
				console.error('Wrong grid config data: ', gridInfo);
				return;
			}


			this.editContainer?.then(() => {
				this.gridInfo = gridInfo;
				this.buildGrid();
				this.buildHeader();
			});
		});

		classClick(this.mainElement, 'grid-edit', this.editHandler);
		classClick(this.mainElement, 'grid-delete', this.deleteHandler);
		classClick(this.mainElement, 'grid-add', this.addHandler);
	};

	protected buildHeader = () => {
		if (!this.headerContainer) return;

		const addButton = `<button class="btn btn-primary grid-add">
            ${iconSvg('plus')} ${I18nFramework.Common_Add?.() || 'Add'}
        </button>`;

		this.headerContainer.setHtml(addButton);
	};

	protected buildGrid = () => {
		if (!this.gridInfo || !this.gridContainer) return;

		const config = this.makeGridConfig(this.gridInfo);
		this.grid = new Grid({
			...config,
			...gridConfig(),
		});

		this.grid.render(this.gridContainer.getEl());
	};

	protected makeGridConfig = (
		gridData: IGridInfo,
	): {columns: TColumn[]; data: (string | ReturnType<typeof html>)[][]} => {
		const gridFields = gridData?.gridFields || [];
		const gridItems = gridData?.items || [];
		const data: (string | ReturnType<typeof html>)[][] = [];
		const selectMap: Record<string, Record<string, string>> = {};
		const columns: TColumn[] = [
			{
				id: '___controls',
				name: '',
			},
		];

		for (const id of gridFields) {
			const fieldInfo = gridData?.fields?.[id];
			const name = fieldInfo?.name || '';
			columns.push({id, name});
			const type = fieldInfo?.type || 'string';

			this.columnsMap[id] = {name, type};
		}

		for (const col of columns) {
			const id = (col.id ?? '') as string;
			const name = (col.name ?? '') as string;
			const fieldInfo = gridData?.fields?.[id];
			const type = fieldInfo?.type;

			if (
				selectMap[name] ||
				(!GridUtils.isTimeZone(type) && !GridUtils.isSelect(type) && !GridUtils.isMap(type))
			) {
				continue;
			}

			const map: Record<string, string> = {};
			const selectArr = GridUtils.getSelectArr(type);

			for (let {text, value} of selectArr) {
				map[value] = text;
			}

			selectMap[name] = map;
		}

		for (const row of gridItems) {
			const idColumn = gridData?.idColumn || 'id';
			const rowId = escape(row?.[idColumn] + '');
			const newRow: (string | ReturnType<typeof html>)[] = [];

			this.dataMap[rowId] = row;

			for (const col of columns) {
				const id = (col.id ?? '') as string;
				const name = (col.name ?? '') as string;
				const fieldInfo = gridData?.fields?.[id];
				const type = fieldInfo?.type;
				const value = row[id] as string;

				if (id === '___controls') {
					newRow.push(
						html(
							`<div class="d-flex gap-2">` +
								`<span class="garnet-control grid-edit" data-id="${rowId}" title="${I18nFramework.Common_Edit?.() || 'Edit'}">${iconSvg('file-text')}</span>` +
								`<span class="garnet-control grid-delete" data-id="${rowId}" title="${I18nFramework.Common_Delete?.() || 'Delete'}">${iconSvg('trash-2')}</span>` +
								`</div>`,
						),
					);
					continue;
				}

				if (type === 'unix_time') {
					newRow.push(printUtDateTime(value));
					continue;
				}

				if (type === 'bool') {
					newRow.push(Number(value) > 0 ? '\u2713' : '');
					continue;
				}

				if (GridUtils.isBoolStr(type)) {
					newRow.push(Number(value) > 0 ? type.bool : '');
					continue;
				}

				if (GridUtils.isTimeZone(type) || GridUtils.isSelect(type) || GridUtils.isMap(type)) {
					newRow.push(selectMap[name]?.[value]);
					continue;
				}

				newRow.push(value);
			}
			data.push(newRow);
		}

		return {columns, data};
	};

	protected renderForm = (data: Record<string, unknown>, isNew: boolean) => {
		const el = this.editContainer?.getEl();
		if (!el || !this.gridInfo) return;

		if (this.formRoot) {
			this.formRoot.unmount();
		}

		this.formRoot = createRoot(el);
		this.formRoot.render(
			React.createElement(FormBuilder, {
				detailsInfo: this.gridInfo,
				data: data,
				isNew: isNew,
				onSuccess: this.handleSuccess,
				onCancel: this.cancelEditHandler,
			} as FormBuilderProps),
		);
	};

	protected cancelEditHandler = () => {
		if (!this.editContainer || !this.gridContainer || !this.grid) return;

		if (this.formRoot) {
			this.formRoot.unmount();
			this.formRoot = null;
		}

		this.editContainer.toggle(false);
		this.editContainer.setHtml('');
		this.isNewItem = false;

		this.gridContainer.toggle(true);
		this.grid.forceRender();
	};

	protected editHandler = (event: MouseEvent, element: HTMLElement): void => {
		const id = element?.dataset?.id;
		if (!id) return;

		const data = this.dataMap[id];
		this.isNewItem = false;
		this.renderForm(data, false);
		this.gridContainer?.toggle(false);
		this.editContainer?.toggle(true);
	};

	protected addHandler = (): void => {
		this.isNewItem = true;
		this.renderForm({}, true);
		this.gridContainer?.toggle(false);
		this.editContainer?.toggle(true);
	};

	protected deleteHandler = async (event: MouseEvent, element: HTMLElement): Promise<void> => {
		const id = element?.dataset?.id;
		if (!id || !this.gridInfo) return;

		const confirmMsg =
			I18nFramework.Common_DeleteConfirm?.() || 'Are you sure you want to delete this item?';
		// TODO: tech debt — replace native confirm() with ConfirmModal. GridTable is a
		// vanilla-JS Component (not React), so useConfirm() hook is unavailable here.
		// Proper fix: imperative ConfirmModal API or migrate GridTable to React.
		if (!confirm(confirmMsg)) return;

		const deleteUrl = this.gridInfo.saveUrl?.replace('~save_', '~delete_');
		if (!deleteUrl) {
			console.error('Delete URL not configured');
			return;
		}

		try {
			const body: Record<string, string | number> = {[this.gridInfo.idColumn]: id};
			const result = await sendPost<typeof body, Record<string, unknown>>(deleteUrl, body);

			if (result.ok) {
				delete this.dataMap[id];
				this.gridInfo.items = this.gridInfo.items.filter((item) => {
					const rowId = item[this.gridInfo!.idColumn] + '';
					return rowId !== id;
				});

				const config = this.makeGridConfig(this.gridInfo);
				this.grid
					.updateConfig({
						...config,
						...gridConfig(),
					})
					.forceRender();
			} else {
				// TODO: tech debt — replace native alert() with showToast/ConfirmModal once
				// GridTable is migrated to React (see deleteHandler note above).
				alert((result as {error?: string}).error || I18nFramework.Common_DeleteError?.() || 'Failed to delete item');
			}
		} catch (e) {
			console.error('Delete error:', e);
			// TODO: tech debt — see above; native alert() until GridTable is React.
			alert(I18nFramework.Common_RequestError?.() || 'Request error');
		}
	};

	protected handleSuccess = (result: IApiSuccessResponse<Record<string, unknown>>): void => {
		if (!this.gridInfo) return;

		const idColumn = this.gridInfo.idColumn;

		if (!result?.data?.[idColumn]) {
			return;
		}

		const id = result.data[idColumn] + '';
		this.dataMap[id] = result.data as Record<string, unknown>;

		if (this.isNewItem) {
			this.gridInfo.items.unshift(result.data as Record<string, unknown>);
			this.isNewItem = false;
		} else {
			this.gridInfo.items = this.gridInfo.items.map((item) => {
				const rowId = item[idColumn] + '';
				return id === rowId ? (result.data as Record<string, unknown>) : item;
			});
		}

		const config = this.makeGridConfig(this.gridInfo);
		this.grid.updateConfig({
			...config,
			...gridConfig(),
		});

		this.cancelEditHandler();
	};
}
