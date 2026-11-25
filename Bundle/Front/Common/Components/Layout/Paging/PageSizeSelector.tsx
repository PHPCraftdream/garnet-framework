import * as React from 'react';
import {I18nFramework as t} from '@framework/I18nGen/I18nFramework';
import {PAGE_SIZE_OPTIONS} from '@common/Utils/Data/pagination/PAGE_SIZE_OPTIONS';

interface Props {
    value: number;
    onChange: (n: number) => void;
}

const renderOption = (n: number) => <option key={n} value={n}>{n}</option>;

/** Controlled page-size selector; persistence belongs to the caller. */
export class PageSizeSelector extends React.Component<Props> {
    /** Sends the selected page size to the caller. */
    private readonly handleChange = (event: React.ChangeEvent<HTMLSelectElement>): void => {
        this.props.onChange(parseInt(event.target.value, 10));
    };

    /** Renders the controlled page-size selector. */
    public render() {
        return (
            <label className="paging-size-label" data-test-id="page-size-selector">
                <span>{t.Grid_PerPage()}</span>
                <select
                    className="paging-size-select"
                    value={this.props.value}
                    onChange={this.handleChange}
                    data-test-id="page-size-select"
                >
                    {PAGE_SIZE_OPTIONS.map(renderOption)}
                </select>
            </label>
        );
    }
}
