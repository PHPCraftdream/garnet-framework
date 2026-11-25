import * as React from 'react';
import {createRoot, Root} from 'react-dom/client';
import {Component} from '@common/Dom/Component/Component';
import {parseJson} from '@common/Utils/Str/parseJson';
import {decode} from 'js-base64';
import {IGridInfo, TGridFieldType} from '@common/Dom/GridTable/Models';
import {GridUtils} from '@common/Dom/GridTable/GridUtils';
import {TColumn} from 'gridjs/dist/src/types';
import {printUtDateTime} from '@common/Utils/Str/printUtDateTime';
import {Grid, html} from 'gridjs';
import 'gridjs/dist/theme/mermaid.min.css';
import {escapeHtml} from '@common/Utils/Str/EscapeHtml';
import {gridConfig} from '@common/Dom/GridTable/GridConfig';
import {classClick} from '@common/Dom/El/ClassClick';
import {DomEl} from '@common/Dom/El/DomEl';
import {FormBuilder, FormBuilderProps} from '@common/Components/Form/FormBuilder';
import {sendPost} from '@common/Api/Send/sendPost';

declare global {
	interface Window {
		__GARNET_CSRF__?: string;
	}
}
import {IApiSuccessResponse} from '@common/Support/Models';
import {I18nFramework} from '@framework/I18nGen/I18nFramework';
import {iconSvg} from '@common/Utils/Ui/iconSvg';

export class GridTable extends Component {
	/** Grid.js instance rendered for this table. */
	protected grid!: Grid;
	/** React root used to render the edit form. */
	protected formRoot: Root | null = null;
	/** Parsed grid configuration. */
	protected gridInfo: IGridInfo | null = null;
	/** Container holding the rendered grid. */
	protected gridContainer: DomEl<HTMLElement> | null = null;
	/** Container holding the edit form. */
	protected editContainer: DomEl<HTMLElement> | null = null;
	/** Container holding grid header actions. */
	protected headerContainer: DomEl<HTMLElement> | null = null;

	/** Row data indexed by escaped identifier. */
	protected dataMap: Record<string, Record<string, unknown>> = {};
	/** Column metadata indexed by field identifier. */
	protected columnsMap: Record<string, {name: string; type: TGridFieldType}> = {};
	/** Whether the current form creates a new row. */
	protected isNewItem: boolean = false;

	/** Initializes containers, grid data, and delegated action handlers. */
	init = () => {
		this.editContainer = this.get('.edit-container');
		this.gridContainer = this.get('.grid-container');
		this.headerContainer = this.get('.header-container');

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

	/** Renders the add action in the grid header. */
	protected buildHeader = () => {
		if (!this.headerContainer) return;

		const addButton = `<button class="btn btn-primary grid-add">
            ${iconSvg('plus')} ${I18nFramework.Common_Add?.() || 'Add'}
        </button>`;

		this.headerContainer.setHtml(addButton);
	};

	/** Creates and renders the Grid.js instance from the current configuration. */
	protected buildGrid = () => {
		if (!this.gridInfo || !this.gridContainer) return;

		const config = this.makeGridConfig(this.gridInfo);
		this.grid = new Grid({
			...config,
			...gridConfig(),
		});

		this.grid.render(this.gridContainer.getEl());
	};

	/** Builds columns and display rows from grid configuration. */
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
			const rowId = escapeHtml(row?.[idColumn] + '');
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
								`<span class="garnet-control grid-edit" data-id="${rowId}" ` +
									`title="${I18nFramework.Common_Edit?.() || 'Edit'}">` +
									`${iconSvg('file-text')}</span>` +
								`<span class="garnet-control grid-delete" data-id="${rowId}" ` +
									`title="${I18nFramework.Common_Delete?.() || 'Delete'}">` +
									`${iconSvg('trash-2')}</span>` +
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

	/** Mounts the form used to create or edit a row. */
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

	/** Closes the edit form and restores the grid. */
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

	/** Opens the form for the selected existing row. */
	protected editHandler = (event: MouseEvent, element: HTMLElement): void => {
		const id = element?.dataset?.id;
		if (!id) return;

		const data = this.dataMap[id];
		this.isNewItem = false;
		this.renderForm(data, false);
		this.gridContainer?.toggle(false);
		this.editContainer?.toggle(true);
	};

	/** Opens an empty form to add a row. */
	protected addHandler = (): void => {
		this.isNewItem = true;
		this.renderForm({}, true);
		this.gridContainer?.toggle(false);
		this.editContainer?.toggle(true);
	};

	/** Deletes the selected row through the configured endpoint. */
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
				alert(
					(result as {error?: string}).error ||
						I18nFramework.Common_DeleteError?.() ||
						'Failed to delete item',
				);
			}
		} catch (e) {
			console.error('Delete error:', e);
			// TODO: tech debt — see above; native alert() until GridTable is React.
			alert(I18nFramework.Common_RequestError?.() || 'Request error');
		}
	};

	/** Updates local grid data after a successful form submission. */
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
