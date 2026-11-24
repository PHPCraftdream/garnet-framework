import * as React from 'react';
import {AntiHookComponent} from '@react-carburetor/core';
import * as Popover from '@radix-ui/react-popover';
import {ComboboxState} from './ComboboxState';

interface ComboboxOption {
    value: string;
    label: string;
}

interface ComboboxProps {
    options: ComboboxOption[];
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    searchPlaceholder?: string;
    emptyText?: string;
    testId?: string;
}

export class Combobox extends AntiHookComponent<ComboboxProps> {
    private readonly ui = new ComboboxState();
    private readonly view = this.connectSelection(() => this.ui.store, state => state);

    private readonly changeSearch = (event: React.ChangeEvent<HTMLInputElement>) => this.ui.setSearch(event.target.value);
    private readonly selectOption = (event: React.MouseEvent<HTMLButtonElement>) => {
        this.props.onChange(event.currentTarget.value);
        this.ui.clearAndClose();
    };
    private readonly renderOption = (option: ComboboxOption) => {
        const {value, testId} = this.props;
        return <button key={option.value} type="button" className="combobox-option" data-selected={option.value === value}
            value={option.value} onClick={this.selectOption} data-test-id={testId ? `${testId}-option-${option.value}` : undefined}>
            <span className="combobox-option-label">{option.label}</span>
            {option.value === value && <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3" className="combobox-check"><polyline points="20 6 9 17 4 12" /></svg>}
        </button>;
    };

    public render() {
        return this.renderContent(this.view() as {open: boolean; search: string});
    }

    private readonly isSelected = (option: ComboboxOption) => option.value === this.props.value;

    private selectedLabel(): string {
        return this.props.options.find(this.isSelected)?.label || '';
    }

    private renderContent({open, search}: {open: boolean; search: string}) {
        const {options, value, placeholder = 'Select...', searchPlaceholder = 'Search...', emptyText = 'No results', testId} = this.props;
        const selectedLabel = this.selectedLabel();
        const filtered = search.trim() ? options.filter(option => option.label.toLowerCase().includes(search.toLowerCase())) : options;
        const trigger = (<button type="button" role="combobox" aria-expanded={open} className="combobox-trigger" data-empty={!value} data-test-id={testId}>
            <span className="combobox-trigger-label">{selectedLabel || placeholder}</span>
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" className="combobox-chevron"><path d="m6 9 6 6 6-6" /></svg>
        </button>);
        return <Popover.Root open={open} onOpenChange={this.ui.setOpen}>
            <Popover.Trigger asChild>
                {trigger}
            </Popover.Trigger>
            <Popover.Portal><Popover.Content className="combobox-content" sideOffset={4} align="start">
                <div className="combobox-search-wrap"><input type="text" className="combobox-search" placeholder={searchPlaceholder}
                    value={search} onChange={this.changeSearch} autoFocus data-test-id={testId ? `${testId}-search` : undefined} /></div>
                <div className="combobox-options">{filtered.length === 0 ? <div className="combobox-empty">{emptyText}</div> : filtered.map(this.renderOption)}</div>
            </Popover.Content></Popover.Portal>
        </Popover.Root>;
    }
}
