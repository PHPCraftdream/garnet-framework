/**
 * Поле с подсказками из готового списка значений.
 */

import * as React from 'react';
import type {IDataListItem} from '@common/Support/Models';
import {Combobox} from '@common/Components/ui/Combobox';


interface DatalistSelectProps {
	column: string;
	value: string;
	items: IDataListItem[];
	disabled: boolean;
	onChange: (value: string) => void;
}

const comboboxOption = (item: IDataListItem) => ({value: String(item.value), label: item.text});
const nativeOption = (item: IDataListItem) => <option key={item.value} value={item.value}>{item.text}</option>;

class NativeDatalistSelect extends React.Component<DatalistSelectProps> {
	private readonly handleChange = (event: React.ChangeEvent<HTMLSelectElement>): void => {
		this.props.onChange(event.target.value);
	};

	public render() {
		const {column, value, items, disabled} = this.props;
		return (
			<select
				className="form-datalist-select"
				value={value}
				disabled={disabled}
				onChange={this.handleChange}
				data-test-id={`form-field-${column}`}
			>
				{items.map(nativeOption)}
			</select>
		);
	}
}

export const DatalistSelect: React.FC<DatalistSelectProps> = ({column, value, items, disabled, onChange}) => {
	if (items.length > 10) {
		return (
			<Combobox
				options={items.map(comboboxOption)}
				value={String(value)}
				onChange={onChange}
				placeholder="..."
				searchPlaceholder="Search..."
				emptyText="—"
				testId={`form-field-${column}`}
			/>
		);
	}
	return <NativeDatalistSelect column={column} value={value} items={items} disabled={disabled} onChange={onChange} />;
};

// --- PhotoField ---
